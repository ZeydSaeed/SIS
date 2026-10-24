<?php

namespace App\Infrastructure\Persistence\Results;

use App\Application\Results\Contracts\PortalPublishedResultsReadRepositoryInterface;
use App\Database\SchemaHelper;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentPortalPublishedResultsReadRepository implements PortalPublishedResultsReadRepositoryInterface
{
    public function forStudents(int $schoolId, array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $query = DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('students', 'students').' as st', 'st.id', '=', 'e.student_id')
            ->join(SchemaHelper::qualified('academic', 'academic_years').' as ay', 'ay.id', '=', 'e.academic_year_id')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 'e.class_id')
            ->where('e.school_id', $schoolId)->whereIn('e.student_id', $studentIds)
            ->orderByDesc('ay.start_date')->orderBy('st.full_name')->orderBy('e.id')
            ->select(['e.id as enrollment_id', 'st.full_name', 'st.student_code', 'ay.name as year_name', 'c.name as class_name']);
        $this->joinPublished($query, 'annual', 'annual_results', 'is_current_official', 'result_version', 'average_weighted_total', 'incomplete', $schoolId);
        $this->joinPublished($query, 'gpa', 'gpa_results', 'is_current_official', 'result_version', 'gpa_value', 'incomplete', $schoolId);
        $this->joinPublished($query, 'transcript', 'transcripts', 'is_current', 'transcript_version', 'transcript_number', 'false', $schoolId);
        $rows = $query->get();
        $terms = $this->publishedTerms($schoolId, $rows->pluck('enrollment_id')->map(static fn ($id): int => (int) $id)->all());

        return $rows->map(fn (object $r): array => [
            'enrollment_id' => (int) $r->enrollment_id,
            'student_name' => (string) $r->full_name,
            'student_code' => (string) $r->student_code,
            'year_name' => (string) $r->year_name,
            'class_name' => (string) $r->class_name,
            'annual' => $this->cell($r, 'annual'),
            'gpa' => $this->cell($r, 'gpa'),
            'transcript' => $this->cell($r, 'transcript'),
            'terms' => $terms[(int) $r->enrollment_id] ?? [],
        ])->filter(static fn (array $row): bool => $row['annual'] !== null || $row['gpa'] !== null || $row['transcript'] !== null || $row['terms'] !== [])->values()->all();
    }

    /** The result must be the current one AND an active publication must snapshot exactly this version. */
    private function joinPublished(Builder $query, string $kind, string $table, string $currentColumn, string $versionColumn, string $valueColumn, string $incomplete, int $schoolId): void
    {
        $publications = SchemaHelper::qualified('results', 'result_publications');
        $query->leftJoin(SchemaHelper::qualified('results', $table)." as {$kind}", static function ($join) use ($kind, $schoolId, $currentColumn): void {
            $join->on("{$kind}.enrollment_id", '=', 'e.id')->where("{$kind}.school_id", $schoolId)->where("{$kind}.{$currentColumn}", true);
        });
        $query->leftJoin("{$publications} as {$kind}_p", static function ($join) use ($kind, $schoolId, $versionColumn): void {
            $join->where("{$kind}_p.school_id", $schoolId)->where("{$kind}_p.kind", $kind)->where("{$kind}_p.status", 'published')
                ->whereColumn("{$kind}_p.academic_year_id", 'e.academic_year_id')
                ->whereRaw("{$kind}_p.published_snapshot @> jsonb_build_array(jsonb_build_object('enrollment_id', e.id, 'version', {$kind}.{$versionColumn}))");
        });
        $incompleteSelect = $incomplete === 'false' ? 'false' : "{$kind}.{$incomplete}";
        $query->addSelect([
            "{$kind}.{$valueColumn} as {$kind}_value", "{$kind}.{$versionColumn} as {$kind}_version", "{$kind}_p.published_at as {$kind}_published_at",
        ])->selectRaw("{$incompleteSelect} as {$kind}_incomplete");
    }

    /**
     * Published term results per enrollment.
     *
     * @param  list<int>  $enrollmentIds
     * @return array<int, list<array{term_name: string, subject_name: string, value: ?string, version: int, incomplete: bool, published_at: string}>>
     */
    private function publishedTerms(int $schoolId, array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }
        $publications = SchemaHelper::qualified('results', 'result_publications');
        $found = DB::table(SchemaHelper::qualified('results', 'term_results').' as t')
            ->join(SchemaHelper::qualified('academic', 'terms').' as tm', 'tm.id', '=', 't.term_id')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as sub', 'sub.id', '=', 't.subject_id')
            ->join("{$publications} as p", static function ($join) use ($schoolId): void {
                $join->where('p.school_id', $schoolId)->where('p.kind', 'term')->where('p.status', 'published')
                    ->whereColumn('p.academic_year_id', 't.academic_year_id')->whereColumn('p.term_id', 't.term_id')->whereColumn('p.subject_id', 't.subject_id')
                    ->whereRaw("p.published_snapshot @> jsonb_build_array(jsonb_build_object('enrollment_id', t.enrollment_id, 'version', t.result_version))");
            })
            ->where('t.school_id', $schoolId)->where('t.is_current_official', true)->whereIn('t.enrollment_id', $enrollmentIds)
            ->orderBy('tm.term_order')->orderBy('sub.name')
            ->get(['t.enrollment_id', 'tm.name as term_name', 'sub.name as subject_name', 't.weighted_total', 't.result_version', 't.incomplete', 'p.published_at']);

        $byEnrollment = [];
        foreach ($found as $r) {
            $byEnrollment[(int) $r->enrollment_id][] = [
                'term_name' => (string) $r->term_name,
                'subject_name' => (string) $r->subject_name,
                'value' => $r->weighted_total === null ? null : (string) $r->weighted_total,
                'version' => (int) $r->result_version,
                'incomplete' => (bool) $r->incomplete,
                'published_at' => (string) $r->published_at,
            ];
        }

        return $byEnrollment;
    }

    /** @return array{value: ?string, version: int, incomplete: bool, published_at: string}|null */
    private function cell(object $r, string $kind): ?array
    {
        $publishedAt = $r->{$kind.'_published_at'};

        return $publishedAt === null ? null : [
            'value' => $r->{$kind.'_value'} === null ? null : (string) $r->{$kind.'_value'},
            'version' => (int) $r->{$kind.'_version'},
            'incomplete' => (bool) $r->{$kind.'_incomplete'},
            'published_at' => (string) $publishedAt,
        ];
    }
}
