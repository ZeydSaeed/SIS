<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS — sis:audit-result-rounding classifies stored results and never writes.
 */
final class AuditResultRoundingCommandPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    private int $version = 0;

    /** @return array<string, mixed> */
    private function seedResults(): array
    {
        $school = $this->createSchool('SCH-RND', 'Rounding audit');
        $g = $this->seedExamGradeGraph($school, suffix: 'RND');
        $typeOne = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $g['exam_id'])->value('exam_type_id');
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('id', $typeOne)->update(['weight_percentage' => 50]);

        // Second exam (weight 50) in the same term, same subject, same student.
        $typeTwo = (int) DB::table(SchemaHelper::qualified('exams', 'exam_types'))->insertGetId([
            'code' => 'FIN'.substr(uniqid(), -4), 'name' => 'Final RND', 'weight_percentage' => 50,
        ]);
        $examTwo = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->insertGetId([
            'academic_year_id' => $g['year_id'], 'school_id' => $school, 'term_id' => $g['term_id'], 'exam_type_id' => $typeTwo,
            'name' => 'Final RND', 'start_date' => '2026-12-01', 'end_date' => '2026-12-10', 'status' => 2,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $sessionTwo = (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->insertGetId([
            'exam_id' => $examTwo, 'school_id' => $school, 'subject_id' => $g['subject_id'], 'session_date' => '2026-12-05',
            'start_time' => '09:00:00', 'end_time' => '11:00:00', 'max_grade' => 100, 'pass_grade' => 50, 'status' => 3,
            'created_at' => now(),
        ]);
        $seatTwo = (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->insertGetId([
            'exam_session_id' => $sessionTwo, 'school_id' => $school, 'enrollment_id' => $g['enrollment_id'],
            'seat_number' => 'B1', 'status' => 1, 'created_at' => now(),
        ]);

        $grade = fn (int $seat, int $session, string $score, bool $current) => (int) DB::table(SchemaHelper::qualified('exams', 'student_grades'))->insertGetId([
            'academic_year_id' => $g['year_id'], 'school_id' => $school, 'exam_enrollment_id' => $seat, 'exam_session_id' => $session,
            'enrollment_id' => $g['enrollment_id'], 'student_id' => $g['student_id'], 'subject_id' => $g['subject_id'],
            'score' => $score, 'max_score' => '100', 'is_absent' => false, 'status' => $current ? 4 : 5, 'is_current' => $current,
            'entered_at' => now(), 'finalized_at' => $current ? now() : null,
        ]);
        $g1 = $grade($g['exam_enrollment_id'], $g['session_id'], '36.01', true);
        $g2 = $grade($seatTwo, $sessionTwo, '63.98', true);
        $g3 = $grade($g['exam_enrollment_id'], $g['session_id'], '80.00', false);
        $g4 = $grade($seatTwo, $sessionTwo, '60.00', false);

        $term = fn (?string $total, array $gradeIds, bool $finalized = false) => $this->termResult($g, $total, $gradeIds, $finalized);
        $misrounded = $term('49.99', [$g1, $g2], finalized: true); // legacy 49.99, exact 50.00
        $drifted = $term('50.00', [$g1, $g2]);                      // legacy gives 49.99 ≠ stored → inputs changed
        $ok = $term('70.00', [$g3, $g4]);                           // 40 + 30
        $t49 = $term('49.00', []);                                  // no pin → source missing
        $t4923 = $term('49.23', []);

        $annualMisrounded = $this->annualResult($g, '49.11', [$t49, $t4923]); // legacy 49.11, exact 49.12
        $annualCascade = $this->annualResult($g, '59.99', [$misrounded, $ok]); // built on a misrounded term row

        return compact('school', 'misrounded', 'drifted', 'ok', 'annualMisrounded', 'annualCascade');
    }

    /** @param  array<string, mixed>  $g  @param  list<int>  $gradeIds */
    private function termResult(array $g, ?string $total, array $gradeIds, bool $finalized): int
    {
        return (int) DB::table(SchemaHelper::qualified('results', 'term_results'))->insertGetId([
            'school_id' => $g['school_id'], 'enrollment_id' => $g['enrollment_id'], 'student_id' => $g['student_id'],
            'academic_year_id' => $g['year_id'], 'term_id' => $g['term_id'], 'subject_id' => $g['subject_id'],
            'result_version' => ++$this->version, 'lifecycle_status' => $finalized ? 2 : 1, 'is_official' => $finalized,
            'weighted_total' => $total, 'source_fingerprint' => 'fp-'.$this->version,
            'policy_pin' => json_encode($gradeIds === [] ? [] : ['source_grade_ids' => $gradeIds]),
            'calculated_at' => now(), 'finalized_at' => $finalized ? now() : null,
        ]);
    }

    /** @param  array<string, mixed>  $g  @param  list<int>  $termIds */
    private function annualResult(array $g, string $average, array $termIds): int
    {
        return (int) DB::table(SchemaHelper::qualified('results', 'annual_results'))->insertGetId([
            'school_id' => $g['school_id'], 'enrollment_id' => $g['enrollment_id'], 'student_id' => $g['student_id'],
            'academic_year_id' => $g['year_id'], 'result_version' => ++$this->version, 'lifecycle_status' => 1,
            'subjects_counted' => count($termIds), 'average_weighted_total' => $average, 'source_fingerprint' => 'fpa-'.$this->version,
            'policy_pin' => json_encode(['source_term_result_ids' => $termIds]), 'calculated_at' => now(),
        ]);
    }

    /** @return array<string, mixed> */
    private function audit(int $schoolId): array
    {
        Artisan::call('sis:audit-result-rounding', ['--school' => $schoolId, '--json' => true]);

        return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, string> */
    private function snapshot(): array
    {
        $out = [];
        foreach (['results.term_results', 'results.annual_results', 'exams.student_grades'] as $table) {
            $out[$table] = (string) DB::selectOne("SELECT md5(string_agg(t::text, '|' ORDER BY t::text)) AS h FROM {$table} t")->h;
        }

        return $out;
    }

    #[Test]
    public function classifies_stored_term_and_annual_results(): void
    {
        $s = $this->seedResults();
        $report = $this->audit($s['school']);

        $term = $report['term'];
        $this->assertSame(5, $term['scanned']);
        $this->assertSame(1, $term['misrounded']);
        $this->assertSame(1, $term['misrounded_official']);
        $this->assertSame(1, $term['misrounded_finalized']);
        $this->assertSame(1, $term['inputs_changed']);
        $this->assertSame(1, $term['ok']);
        $this->assertSame(2, $term['source_missing']);
        $this->assertSame(1, $term['crosses_pass_mark']);
        $this->assertSame($s['misrounded'], $term['rows'][0]['result_id']);
        $this->assertSame('49.99', $term['rows'][0]['stored']);
        $this->assertSame('50.00', $term['rows'][0]['expected']);
        $this->assertSame('0.01', $term['rows'][0]['difference']);
        $this->assertSame('finalized', $term['rows'][0]['lifecycle_status']);
        $this->assertArrayNotHasKey('student_id', $term['rows'][0]);

        $annual = $report['annual'];
        $this->assertSame(2, $annual['scanned']);
        $this->assertSame(1, $annual['source_term_misrounded']);
        $this->assertContains($s['annualMisrounded'], array_column($annual['rows'], 'result_id'));
        $row = $annual['rows'][array_search($s['annualMisrounded'], array_column($annual['rows'], 'result_id'), true)];
        $this->assertSame(['49.11', '49.12'], [$row['stored'], $row['expected']]);
    }

    #[Test]
    public function audit_writes_nothing_and_is_reproducible(): void
    {
        $s = $this->seedResults();
        $before = $this->snapshot();

        $first = $this->audit($s['school']);
        $second = $this->audit($s['school']);

        $this->assertSame($before, $this->snapshot());
        $this->assertSame($first['fingerprint'], $second['fingerprint']);
    }
}
