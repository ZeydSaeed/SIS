<?php

namespace App\Console\Commands;

use App\Database\SchemaHelper;
use App\Domain\Results\Support\ResultRounding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only audit: stored term / annual results whose total differs under exact half-up rounding (EXAMIS U1).
 *
 * Everything runs inside one READ ONLY transaction that is always rolled back — PostgreSQL itself rejects any write.
 * Each row is recomputed from the source rows pinned in its policy_pin:
 *  - legacy  = the former float algorithm on those inputs; it must reproduce the stored value, otherwise the inputs
 *              changed since (e.g. an exam-type weight was edited) and the row is reported as "inputs_changed";
 *  - exact   = the corrected algorithm; "misrounded" when legacy = stored but exact ≠ stored.
 * Output carries result ids and academic scope only (no student names).
 *
 * php artisan sis:audit-result-rounding [--school=ID] [--json]
 */
class AuditResultRoundingCommand extends Command
{
    protected $signature = 'sis:audit-result-rounding
                            {--school= : Only this school id}
                            {--json : Print the full findings as JSON}';

    protected $description = 'Read-only: stored term/annual results that exact half-up rounding would change';

    private const CHUNK = 1000;

    public function handle(): int
    {
        DB::beginTransaction();
        DB::statement('SET TRANSACTION READ ONLY');

        try {
            $report = ['term' => $this->emptySection(), 'annual' => $this->emptySection()];
            foreach ($this->schoolIds() as $schoolId) {
                DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
                $misroundedTerms = $this->auditTermResults($schoolId, $report['term']);
                $this->auditAnnualResults($schoolId, $misroundedTerms, $report['annual']);
            }
        } finally {
            DB::rollBack();
        }

        $report['fingerprint'] = hash('sha256', json_encode([$report['term']['rows'], $report['annual']['rows']]));
        $this->option('json') ? $this->line((string) json_encode($report, JSON_PRETTY_PRINT)) : $this->render($report);

        return $report['term']['misrounded'] + $report['annual']['misrounded'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<int> */
    private function schoolIds(): array
    {
        if ($this->option('school') !== null) {
            return [(int) $this->option('school')];
        }

        return DB::table(SchemaHelper::qualified('organization', 'schools'))->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    /** @return array<string, mixed> */
    private function emptySection(): array
    {
        return ['scanned' => 0, 'ok' => 0, 'misrounded' => 0, 'misrounded_official' => 0, 'misrounded_finalized' => 0,
            'crosses_pass_mark' => 0, 'inputs_changed' => 0, 'source_missing' => 0, 'source_term_misrounded' => 0, 'rows' => []];
    }

    /**
     * @param  array<string, mixed>  $section
     * @return array<int, true> misrounded term result ids
     */
    private function auditTermResults(int $schoolId, array &$section): array
    {
        $misrounded = [];
        DB::table(SchemaHelper::qualified('results', 'term_results'))
            ->where('school_id', $schoolId)
            ->select(['id', 'school_id', 'academic_year_id', 'term_id', 'subject_id', 'enrollment_id', 'lifecycle_status',
                'is_official', 'is_current_operational', 'is_current_official', 'weighted_total', 'pass_fail', 'policy_pin'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use ($schoolId, &$section, &$misrounded): void {
                $sources = $this->sourceGrades($schoolId, $rows->flatMap(fn (object $r): array => $this->pinnedIds($r->policy_pin, 'source_grade_ids'))->unique()->values()->all());
                foreach ($rows as $row) {
                    $section['scanned']++;
                    $ids = $this->pinnedIds($row->policy_pin, 'source_grade_ids');
                    $inputs = array_values(array_intersect_key($sources, array_flip($ids)));
                    if ($ids === [] || count($inputs) !== count($ids)) {
                        $section['source_missing']++;

                        continue;
                    }

                    $stored = $row->weighted_total !== null ? (string) $row->weighted_total : null;
                    $verdict = $this->verdict($stored, $this->legacyTermTotal($inputs), $this->exactTermTotal($inputs));
                    $section[$verdict]++;
                    if ($verdict !== 'misrounded') {
                        continue;
                    }

                    $expected = $this->exactTermTotal($inputs);
                    $crosses = $this->crossesPassMark($stored, $expected, $inputs);
                    $misrounded[(int) $row->id] = true;
                    $section['misrounded_official'] += (bool) $row->is_official ? 1 : 0;
                    $section['misrounded_finalized'] += (int) $row->lifecycle_status === 2 ? 1 : 0;
                    $section['crosses_pass_mark'] += $crosses ? 1 : 0;
                    $section['rows'][] = $this->finding($row, $stored, $expected) + [
                        'term_id' => (int) $row->term_id,
                        'subject_id' => (int) $row->subject_id,
                        'stored_pass_fail' => $row->pass_fail !== null ? (int) $row->pass_fail : null,
                        'crosses_session_pass_mark' => $crosses,
                    ];
                }
            });

        return $misrounded;
    }

    /**
     * @param  array<int, true>  $misroundedTerms
     * @param  array<string, mixed>  $section
     */
    private function auditAnnualResults(int $schoolId, array $misroundedTerms, array &$section): void
    {
        DB::table(SchemaHelper::qualified('results', 'annual_results'))
            ->where('school_id', $schoolId)
            ->select(['id', 'school_id', 'academic_year_id', 'enrollment_id', 'lifecycle_status', 'is_official',
                'is_current_operational', 'is_current_official', 'average_weighted_total', 'policy_pin'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($rows) use ($schoolId, $misroundedTerms, &$section): void {
                $allIds = $rows->flatMap(fn (object $r): array => $this->pinnedIds($r->policy_pin, 'source_term_result_ids'))->unique()->values()->all();
                $totals = $allIds === [] ? [] : DB::table(SchemaHelper::qualified('results', 'term_results'))
                    ->where('school_id', $schoolId)
                    ->whereIn('id', $allIds)
                    ->pluck('weighted_total', 'id')
                    ->all();

                foreach ($rows as $row) {
                    $section['scanned']++;
                    $ids = $this->pinnedIds($row->policy_pin, 'source_term_result_ids');
                    if ($ids === [] || count(array_intersect_key($totals, array_flip($ids))) !== count($ids)) {
                        $section['source_missing']++;

                        continue;
                    }
                    if (array_intersect_key($misroundedTerms, array_flip($ids)) !== []) {
                        $section['source_term_misrounded']++;
                    }

                    $values = [];
                    foreach ($ids as $id) {
                        if ($totals[$id] !== null) {
                            $values[] = (string) $totals[$id];
                        }
                    }
                    $stored = $row->average_weighted_total !== null ? (string) $row->average_weighted_total : null;
                    $expected = $values !== [] ? ResultRounding::mean($values) : null;
                    $legacy = $values !== []
                        ? number_format(floor((array_sum(array_map('floatval', $values)) / count($values)) * 100 + 0.5) / 100, 2, '.', '')
                        : null;

                    $verdict = $this->verdict($stored, $legacy, $expected);
                    $section[$verdict]++;
                    if ($verdict === 'misrounded') {
                        $section['misrounded_official'] += (bool) $row->is_official ? 1 : 0;
                        $section['misrounded_finalized'] += (int) $row->lifecycle_status === 2 ? 1 : 0;
                        $section['rows'][] = $this->finding($row, $stored, $expected);
                    }
                }
            });
    }

    /** ok | misrounded | inputs_changed */
    private function verdict(?string $stored, ?string $legacy, ?string $exact): string
    {
        if (! $this->same($legacy, $stored)) {
            return 'inputs_changed';
        }

        return $this->same($exact, $stored) ? 'ok' : 'misrounded';
    }

    private function same(?string $a, ?string $b): bool
    {
        return $a === null || $b === null ? $a === $b : bccomp($a, $b, 2) === 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function finding(object $row, ?string $stored, ?string $expected): array
    {
        return [
            'result_id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'academic_year_id' => (int) $row->academic_year_id,
            'enrollment_id' => (int) $row->enrollment_id,
            'stored' => $stored,
            'expected' => $expected,
            'difference' => $stored !== null && $expected !== null ? bcsub($expected, $stored, 2) : null,
            'lifecycle_status' => match ((int) $row->lifecycle_status) {
                1 => 'calculated', 2 => 'finalized', 3 => 'superseded', default => 'unknown'
            },
            'is_official' => (bool) $row->is_official,
            'is_current' => (bool) $row->is_current_operational || (bool) $row->is_current_official,
        ];
    }

    /** @return list<int> */
    private function pinnedIds(mixed $policyPin, string $key): array
    {
        $pin = is_string($policyPin) ? json_decode($policyPin, true) : (array) $policyPin;
        $ids = is_array($pin) && is_array($pin[$key] ?? null) ? $pin[$key] : [];

        return array_values(array_map('intval', $ids));
    }

    /**
     * Source grades as they were used (scores of a grade row never change; corrections are new rows).
     *
     * @param  list<int>  $gradeIds
     * @return array<int, object>
     */
    private function sourceGrades(int $schoolId, array $gradeIds): array
    {
        if ($gradeIds === []) {
            return [];
        }

        return DB::table(SchemaHelper::qualified('exams', 'student_grades').' as g')
            ->join(SchemaHelper::qualified('exams', 'exam_sessions').' as es', 'es.id', '=', 'g.exam_session_id')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->join(SchemaHelper::qualified('exams', 'exam_types').' as et', 'et.id', '=', 'ex.exam_type_id')
            ->where('g.school_id', $schoolId)
            ->whereIn('g.id', $gradeIds)
            ->get(['g.id', 'g.score', 'g.max_score', 'g.is_absent', 'et.weight_percentage', 'es.pass_grade', 'es.max_grade'])
            ->keyBy(fn (object $g): int => (int) $g->id)
            ->all();
    }

    /** @param  list<object>  $inputs */
    private function legacyTermTotal(array $inputs): ?string
    {
        $numerator = 0.0;
        $denominator = 0.0;
        foreach ($inputs as $g) {
            if ((bool) $g->is_absent || $g->score === null || (float) $g->max_score <= 0.0) {
                continue;
            }
            $weight = (float) $g->weight_percentage;
            $numerator += ((float) $g->score / (float) $g->max_score) * 100.0 * ($weight / 100.0);
            $denominator += $weight;
        }

        return $denominator > 0.0 ? number_format(floor($numerator * 100 + 0.5) / 100, 2, '.', '') : null;
    }

    /** @param  list<object>  $inputs */
    private function exactTermTotal(array $inputs): ?string
    {
        $terms = [];
        $weight = 0;
        foreach ($inputs as $g) {
            if ((bool) $g->is_absent || $g->score === null || (float) $g->max_score <= 0.0) {
                continue;
            }
            $terms[] = ['score' => (string) $g->score, 'max' => (string) $g->max_score, 'weight' => (int) $g->weight_percentage];
            $weight += (int) $g->weight_percentage;
        }

        return $weight > 0 ? ResultRounding::weightedPercentage($terms) : null;
    }

    /**
     * Whether a session pass mark (pass_grade / max_grade, as a percentage) lies between the stored and the exact total.
     * There is no term-level pass threshold in the calculation path, so session pass marks are the only policy at hand.
     *
     * @param  list<object>  $inputs
     */
    private function crossesPassMark(?string $stored, ?string $expected, array $inputs): bool
    {
        if ($stored === null || $expected === null) {
            return false;
        }
        foreach ($inputs as $g) {
            if ((int) $g->max_grade <= 0) {
                continue;
            }
            $mark = bcdiv(bcmul((string) $g->pass_grade, '100', 6), (string) $g->max_grade, 6);
            if ((bccomp($stored, $mark, 6) >= 0) !== (bccomp($expected, $mark, 6) >= 0)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $report */
    private function render(array $report): void
    {
        foreach (['term' => 'Term results', 'annual' => 'Annual results'] as $key => $title) {
            $s = $report[$key];
            $this->components->twoColumnDetail("<options=bold>{$title}</>");
            foreach (['scanned', 'ok', 'misrounded', 'misrounded_official', 'misrounded_finalized', 'crosses_pass_mark',
                'inputs_changed', 'source_missing', 'source_term_misrounded'] as $metric) {
                if ($key === 'annual' && $metric === 'crosses_pass_mark') {
                    continue;
                }
                if ($key === 'term' && $metric === 'source_term_misrounded') {
                    continue;
                }
                $this->components->twoColumnDetail($metric, (string) $s[$metric]);
            }
            if ($s['rows'] !== []) {
                $this->table(array_keys($s['rows'][0]), array_map(
                    static fn (array $r): array => array_map(static fn (mixed $v): string => is_bool($v) ? ($v ? 'yes' : 'no') : (string) ($v ?? '—'), $r),
                    $s['rows'],
                ));
            }
        }
        $this->components->twoColumnDetail('fingerprint', $report['fingerprint']);
        $this->components->info('Read-only: nothing was written (READ ONLY transaction, rolled back).');
    }
}
