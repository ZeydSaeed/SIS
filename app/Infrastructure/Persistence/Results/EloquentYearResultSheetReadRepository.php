<?php

namespace App\Infrastructure\Persistence\Results;

use App\Application\Results\Contracts\YearResultSheetReadRepositoryInterface;
use App\Database\SchemaHelper;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Results\ValueObjects\GpaScope;
use Illuminate\Support\Facades\DB;

final class EloquentYearResultSheetReadRepository implements YearResultSheetReadRepositoryInterface
{
    public function rows(int $schoolId, int $academicYearId, int $classId, ?int $sectionId): array
    {
        $terms = SchemaHelper::qualified('results', 'term_results');
        $current = static fn (string $alias, string $flag, bool $gpa = false) => static function ($join) use ($alias, $flag, $schoolId, $academicYearId, $gpa): void {
            $join->on("{$alias}.enrollment_id", '=', 'e.id')
                ->where("{$alias}.school_id", $schoolId)->where("{$alias}.academic_year_id", $academicYearId)->where("{$alias}.{$flag}", true);
            if ($gpa) {
                $join->where("{$alias}.gpa_scope", GpaScope::AcademicYear->value);
            }
        };
        $annual = SchemaHelper::qualified('results', 'annual_results');
        $gpa = SchemaHelper::qualified('results', 'gpa_results');
        $ranking = $this->currentRanking($schoolId, $academicYearId, $classId);
        $entries = SchemaHelper::qualified('results', 'ranking_snapshot_entries');

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('students', 'students').' as st', 'st.id', '=', 'e.student_id')
            ->join(SchemaHelper::qualified('enrollment', 'sections').' as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin("{$annual} as ao", $current('ao', 'is_current_operational'))
            ->leftJoin("{$annual} as af", $current('af', 'is_current_official'))
            ->leftJoin("{$gpa} as go", $current('go', 'is_current_operational', true))
            ->leftJoin("{$gpa} as gf", $current('gf', 'is_current_official', true))
            ->leftJoin(SchemaHelper::qualified('results', 'transcripts').' as tr', static function ($join) use ($schoolId, $academicYearId): void {
                $join->on('tr.enrollment_id', '=', 'e.id')->where('tr.school_id', $schoolId)->where('tr.academic_year_id', $academicYearId)->where('tr.is_current', true);
            })
            ->leftJoin("{$entries} as rk", static function ($join) use ($ranking): void {
                $join->on('rk.enrollment_id', '=', 'e.id')->where('rk.ranking_snapshot_id', $ranking['id'] ?? 0);
            })
            ->where('e.school_id', $schoolId)->where('e.academic_year_id', $academicYearId)->where('e.class_id', $classId)
            ->when($sectionId !== null, static fn ($q) => $q->where('e.section_id', $sectionId))
            ->where('e.status', EnrollmentStatus::ACTIVE)->whereNull('e.effective_to')
            ->orderBy('sc.name')->orderBy('st.full_name')->orderBy('e.id')
            ->select([
                'e.id as enrollment_id', 'st.full_name', 'st.student_code', 'sc.name as section_name',
                'ao.result_version as ao_version', 'ao.average_weighted_total as ao_value', 'ao.incomplete as ao_incomplete',
                'af.result_version as af_version', 'af.average_weighted_total as af_value', 'af.incomplete as af_incomplete',
                'go.result_version as go_version', 'go.gpa_value as go_value', 'go.incomplete as go_incomplete',
                'gf.result_version as gf_version', 'gf.gpa_value as gf_value', 'gf.incomplete as gf_incomplete',
                'tr.transcript_number', 'tr.transcript_version', 'rk.rank_position',
            ])
            ->selectRaw("(SELECT count(*) FROM {$terms} t WHERE t.enrollment_id = e.id AND t.school_id = ? AND t.academic_year_id = ? AND t.is_current_operational) AS terms_operational", [$schoolId, $academicYearId])
            ->selectRaw("(SELECT count(*) FROM {$terms} t WHERE t.enrollment_id = e.id AND t.school_id = ? AND t.academic_year_id = ? AND t.is_current_official) AS terms_official", [$schoolId, $academicYearId])
            ->selectRaw('(tr.id IS NOT NULL AND gf.id IS NOT NULL AND tr.issued_at >= gf.finalized_at) AS transcript_up_to_date')
            ->get()
            ->map(static fn (object $r): array => [
                'enrollment_id' => (int) $r->enrollment_id,
                'student_name' => (string) $r->full_name,
                'student_code' => (string) $r->student_code,
                'section_name' => (string) $r->section_name,
                'term_results' => ['operational' => (int) $r->terms_operational, 'official' => (int) $r->terms_official],
                'annual' => ['operational' => self::cell($r, 'ao'), 'official' => self::cell($r, 'af')],
                'gpa' => ['operational' => self::cell($r, 'go'), 'official' => self::cell($r, 'gf')],
                'rank' => $r->rank_position !== null ? (int) $r->rank_position : null,
                'transcript' => $r->transcript_number === null ? null : ['number' => (string) $r->transcript_number, 'version' => (int) $r->transcript_version, 'up_to_date' => (bool) $r->transcript_up_to_date],
            ])
            ->all();
    }

    public function currentRanking(int $schoolId, int $academicYearId, int $classId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('results', 'ranking_snapshots'))
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('class_id', $classId)->where('is_current', true)
            ->first(['id', 'snapshot_version', 'participant_count', 'calculated_at']);

        return $row === null ? null : ['id' => (int) $row->id, 'version' => (int) $row->snapshot_version, 'participants' => (int) $row->participant_count, 'calculated_at' => (string) $row->calculated_at];
    }

    /** @return array{version: int, value: ?string, incomplete: bool}|null */
    private static function cell(object $r, string $prefix): ?array
    {
        $version = $r->{$prefix.'_version'};
        $value = $r->{$prefix.'_value'};

        return $version === null ? null : ['version' => (int) $version, 'value' => $value !== null ? (string) $value : null, 'incomplete' => (bool) $r->{$prefix.'_incomplete'}];
    }
}
