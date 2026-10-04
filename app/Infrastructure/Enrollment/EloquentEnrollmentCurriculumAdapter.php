<?php

namespace App\Infrastructure\Enrollment;

use App\Database\SchemaHelper;
use App\Domain\Enrollment\Contracts\EnrollmentCurriculumPort;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentEnrollmentCurriculumAdapter implements EnrollmentCurriculumPort
{
    private const ACTIVE = 1;

    public function subjectInGoverningCurriculum(
        int $schoolId,
        int $academicYearId,
        int $classId,
        ?int $departmentId,
        int $subjectId,
    ): bool {
        return $this->governingSubjectsQuery($schoolId, $academicYearId, $classId, $departmentId)
            ->where('cs.subject_id', $subjectId)
            ->exists();
    }

    public function requiredSubjectIdsForPlacement(
        int $schoolId,
        int $academicYearId,
        int $classId,
        ?int $departmentId,
    ): array {
        return $this->governingSubjectsQuery($schoolId, $academicYearId, $classId, $departmentId)
            ->where('cs.is_required', true)
            ->orderBy('cs.subject_order')
            ->pluck('cs.subject_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function enrollmentIdsGovernedBy(int $schoolId, int $curriculumId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as cl', 'cl.id', '=', 'e.class_id')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', function ($join): void {
                $join->on('c.grade_level_id', '=', 'cl.grade_level_id')
                    ->on('c.academic_year_id', '=', 'e.academic_year_id')
                    ->on('c.school_id', '=', 'e.school_id');
            })
            ->leftJoin(SchemaHelper::qualified('vocational', 'specializations').' as sp', 'sp.id', '=', 'c.specialization_id')
            ->where('c.id', $curriculumId)
            ->where('c.status', self::ACTIVE)
            ->where('e.school_id', $schoolId)
            ->where('e.status', EnrollmentStatus::ACTIVE)
            ->whereNull('e.effective_to')
            ->where(function (Builder $scope): void {
                $scope->whereNull('c.specialization_id')
                    ->orWhereColumn('sp.department_id', 'e.department_id');
            })
            ->orderBy('e.id')
            ->pluck('e.id')
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * Active subject links of every active curriculum governing the placement:
     * same school, year and class grade level; a specialization-bound curriculum
     * also needs its specialization's department to equal the enrollment department.
     */
    private function governingSubjectsQuery(
        int $schoolId,
        int $academicYearId,
        int $classId,
        ?int $departmentId,
    ): Builder {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as cl', 'cl.grade_level_id', '=', 'c.grade_level_id')
            ->leftJoin(SchemaHelper::qualified('vocational', 'specializations').' as sp', 'sp.id', '=', 'c.specialization_id')
            ->where('cl.id', $classId)
            ->where('cs.status', self::ACTIVE)
            ->where('c.status', self::ACTIVE)
            ->where('c.school_id', $schoolId)
            ->where('c.academic_year_id', $academicYearId)
            ->where(function (Builder $scope) use ($departmentId): void {
                $scope->whereNull('c.specialization_id');
                if ($departmentId !== null) {
                    $scope->orWhere('sp.department_id', $departmentId);
                }
            });
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
