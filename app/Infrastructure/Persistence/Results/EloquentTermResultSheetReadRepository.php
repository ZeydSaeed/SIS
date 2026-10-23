<?php

namespace App\Infrastructure\Persistence\Results;

use App\Application\Results\Contracts\TermResultSheetReadRepositoryInterface;
use App\Database\SchemaHelper;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Domain\Exams\ValueObjects\GradeStatus;
use Illuminate\Support\Facades\DB;

final class EloquentTermResultSheetReadRepository implements TermResultSheetReadRepositoryInterface
{
    public function terms(int $academicYearId): array
    {
        return DB::table(SchemaHelper::qualified('academic', 'terms'))
            ->where('academic_year_id', $academicYearId)->orderBy('term_order')->orderBy('id')
            ->get(['id', 'name'])
            ->map(static fn (object $r): array => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }

    public function classes(int $schoolId, int $academicYearId): array
    {
        $classes = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('status', EnrollmentStatus::ACTIVE)
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);
        $sections = $classes->isEmpty() ? collect() : DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->whereIn('class_id', $classes->pluck('id'))->where('status', EnrollmentStatus::ACTIVE)
            ->orderBy('name')->orderBy('id')->get(['id', 'class_id', 'name'])->groupBy('class_id');

        return $classes->map(static fn (object $c): array => [
            'id' => (int) $c->id,
            'name' => (string) $c->name,
            'sections' => ($sections[$c->id] ?? collect())->map(static fn (object $s): array => ['id' => (int) $s->id, 'name' => (string) $s->name])->values()->all(),
        ])->all();
    }

    public function termInYear(int $termId, int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'terms'))->where('id', $termId)->where('academic_year_id', $academicYearId)->exists();
    }

    public function classInSchoolYear(int $classId, int $schoolId, int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->exists();
    }

    public function sectionInClass(int $sectionId, int $classId): bool
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'sections'))->where('id', $sectionId)->where('class_id', $classId)->exists();
    }

    public function subjects(int $schoolId, int $academicYearId, int $termId): array
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects').' as s')
            ->whereIn('s.id', DB::table(SchemaHelper::qualified('exams', 'exam_sessions').' as es')
                ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
                ->where('ex.school_id', $schoolId)->where('ex.academic_year_id', $academicYearId)->where('ex.term_id', $termId)
                ->where('ex.status', '<>', ExamStatus::Cancelled->value)->where('es.status', '<>', ExamSessionStatus::Cancelled->value)
                ->select('es.subject_id'))
            ->orderBy('s.name')->orderBy('s.id')
            ->get(['s.id', 's.name'])
            ->map(static fn (object $r): array => ['id' => (int) $r->id, 'name' => (string) $r->name])
            ->all();
    }

    public function rows(int $schoolId, int $academicYearId, int $termId, int $subjectId, int $classId, ?int $sectionId): array
    {
        $sessions = DB::table(SchemaHelper::qualified('exams', 'exam_sessions').' as es')
            ->join(SchemaHelper::qualified('exams', 'exams').' as ex', 'ex.id', '=', 'es.exam_id')
            ->where('ex.school_id', $schoolId)->where('ex.academic_year_id', $academicYearId)->where('ex.term_id', $termId)
            ->where('ex.status', '<>', ExamStatus::Cancelled->value)
            ->where('es.subject_id', $subjectId)->where('es.status', '<>', ExamSessionStatus::Cancelled->value)
            ->pluck('es.id')->map(static fn ($id): int => (int) $id)->all();
        if ($sessions === []) {
            return [];
        }

        $in = implode(',', $sessions); // integers from the query above
        $activeSeat = implode(',', array_map(
            static fn (ExamEnrollmentStatus $s): int => $s->value,
            array_filter(ExamEnrollmentStatus::cases(), static fn (ExamEnrollmentStatus $s): bool => $s->isActiveSeat()),
        ));
        $seats = SchemaHelper::qualified('exams', 'exam_enrollments');
        $grades = SchemaHelper::qualified('exams', 'student_grades');
        $results = SchemaHelper::qualified('results', 'term_results');
        $current = static fn (string $alias, string $flag) => static function ($join) use ($alias, $flag, $schoolId, $termId, $subjectId): void {
            $join->on("{$alias}.enrollment_id", '=', 'e.id')
                ->where("{$alias}.school_id", $schoolId)->where("{$alias}.term_id", $termId)
                ->where("{$alias}.subject_id", $subjectId)->where("{$alias}.{$flag}", true);
        };

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('students', 'students').' as st', 'st.id', '=', 'e.student_id')
            ->join(SchemaHelper::qualified('enrollment', 'sections').' as sc', 'sc.id', '=', 'e.section_id')
            ->leftJoin("{$results} as op", $current('op', 'is_current_operational'))
            ->leftJoin("{$results} as off", $current('off', 'is_current_official'))
            ->where('e.school_id', $schoolId)->where('e.academic_year_id', $academicYearId)->where('e.class_id', $classId)
            ->when($sectionId !== null, static fn ($q) => $q->where('e.section_id', $sectionId))
            ->where('e.status', EnrollmentStatus::ACTIVE)->whereNull('e.effective_to')
            ->whereRaw("EXISTS (SELECT 1 FROM {$seats} ee WHERE ee.enrollment_id = e.id AND ee.exam_session_id IN ({$in}) AND ee.status IN ({$activeSeat}))")
            ->orderBy('sc.name')->orderBy('st.full_name')->orderBy('e.id')
            ->select([
                'e.id as enrollment_id', 'st.full_name', 'st.student_code', 'sc.name as section_name',
                'op.result_version as op_version', 'op.weighted_total as op_total', 'op.incomplete as op_incomplete',
                'off.result_version as off_version', 'off.weighted_total as off_total', 'off.incomplete as off_incomplete',
            ])
            ->selectRaw("(SELECT count(*) FROM {$seats} ee WHERE ee.enrollment_id = e.id AND ee.exam_session_id IN ({$in}) AND ee.status IN ({$activeSeat})) AS seats")
            ->selectRaw("(SELECT count(*) FROM {$grades} g WHERE g.enrollment_id = e.id AND g.academic_year_id = ? AND g.exam_session_id IN ({$in}) AND g.is_current) AS current_grades", [$academicYearId])
            ->selectRaw("(SELECT count(*) FROM {$grades} g WHERE g.enrollment_id = e.id AND g.academic_year_id = ? AND g.exam_session_id IN ({$in}) AND g.is_current AND g.status = ?) AS finalized_grades", [$academicYearId, GradeStatus::Finalized->value])
            ->get()
            ->map(static fn (object $r): array => [
                'enrollment_id' => (int) $r->enrollment_id,
                'student_name' => (string) $r->full_name,
                'student_code' => (string) $r->student_code,
                'section_name' => (string) $r->section_name,
                'seats' => (int) $r->seats,
                'current_grades' => (int) $r->current_grades,
                'finalized_grades' => (int) $r->finalized_grades,
                'operational' => $r->op_version === null ? null : ['version' => (int) $r->op_version, 'weighted_total' => $r->op_total !== null ? (string) $r->op_total : null, 'incomplete' => (bool) $r->op_incomplete],
                'official' => $r->off_version === null ? null : ['version' => (int) $r->off_version, 'weighted_total' => $r->off_total !== null ? (string) $r->off_total : null, 'incomplete' => (bool) $r->off_incomplete],
            ])
            ->all();
    }
}
