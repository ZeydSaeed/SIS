<?php

namespace App\Infrastructure\Teachers;

use App\Database\SchemaHelper;
use App\Domain\Teachers\Contracts\TeachingPlacementCatalogPort;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Placement checks for teaching assignments (same curriculum rule as enrollment: active curriculum + link). */
final class EloquentTeachingPlacementCatalogAdapter implements TeachingPlacementCatalogPort
{
    private const ACTIVE = 1;

    public function branchInSchool(int $branchId, int $schoolId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('id', $branchId)
            ->where('school_id', $schoolId)
            ->where('status', self::ACTIVE)
            ->exists();
    }

    public function departmentInBranch(int $departmentId, int $branchId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('id', $departmentId)
            ->where('branch_id', $branchId)
            ->where('status', self::ACTIVE)
            ->exists();
    }

    public function classInSchoolYear(int $classId, int $schoolId, int $academicYearId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', self::ACTIVE)
            ->exists();
    }

    public function sectionInClass(int $sectionId, int $classId): bool
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->where('id', $sectionId)
            ->where('class_id', $classId)
            ->where('status', self::ACTIVE)
            ->exists();
    }

    public function subjectInCurriculum(int $schoolId, int $academicYearId, int $branchId, ?int $departmentId, int $subjectId): bool
    {
        $this->bindSchool($schoolId);
        $departments = SchemaHelper::qualified('organization', 'departments');

        return DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects').' as cs')
            ->join(SchemaHelper::qualified('curriculum', 'curricula').' as c', 'c.id', '=', 'cs.curriculum_id')
            ->where('cs.subject_id', $subjectId)
            ->where('cs.status', self::ACTIVE)
            ->where('c.status', self::ACTIVE)
            ->where('c.school_id', $schoolId)
            ->where('c.academic_year_id', $academicYearId)
            ->where(function (Builder $scope) use ($branchId, $departmentId, $departments): void {
                // A general curriculum (no department) applies everywhere in the school.
                $scope->whereNull('c.department_id');
                if ($departmentId !== null) {
                    $scope->orWhere('c.department_id', $departmentId);

                    return;
                }
                // Branch-wide assignment: the subject belongs to a curriculum of any department of the branch.
                $scope->orWhereIn('c.department_id', fn (Builder $q) => $q->select('id')->from($departments)->where('branch_id', $branchId));
            })
            ->exists();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
