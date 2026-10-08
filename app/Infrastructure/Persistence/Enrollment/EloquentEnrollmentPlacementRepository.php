<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Database\SchemaHelper;
use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Infrastructure\Persistence\Eloquent\EnrollmentClassRecord;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Infrastructure\Persistence\Eloquent\EnrollmentSectionRecord;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use Illuminate\Support\Facades\DB;

final class EloquentEnrollmentPlacementRepository implements EnrollmentPlacementRepositoryInterface
{
    public function academicYearBounds(int $academicYearId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('id', $academicYearId)->first(['start_date', 'end_date']);

        return $row === null ? null : ['start' => substr((string) $row->start_date, 0, 10), 'end' => substr((string) $row->end_date, 0, 10)];
    }

    public function hasActiveCurriculum(int $departmentId, int $classId, int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'curricula').' as c')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as cl', 'cl.grade_level_id', '=', 'c.grade_level_id')
            ->where('cl.id', $classId)
            ->where('c.department_id', $departmentId)
            ->where('c.academic_year_id', $academicYearId)
            ->where('c.status', 1)
            ->exists();
    }

    public function studentBelongsToSchool(int $studentId, int $schoolId): bool
    {
        $record = StudentRecord::query()->find($studentId);
        if ($record === null || $record->school_id === null) {
            return false;
        }

        return (int) $record->school_id === $schoolId;
    }

    public function classBelongsToSchool(int $classId, int $schoolId, int $academicYearId): bool
    {
        return EnrollmentClassRecord::query()
            ->whereKey($classId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }

    public function sectionBelongsToClass(int $sectionId, int $classId): bool
    {
        return EnrollmentSectionRecord::query()
            ->whereKey($sectionId)
            ->where('class_id', $classId)
            ->exists();
    }

    public function classIdForSection(int $sectionId): ?int
    {
        $classId = EnrollmentSectionRecord::query()
            ->whereKey($sectionId)
            ->where('status', 1)
            ->value('class_id');

        return $classId !== null ? (int) $classId : null;
    }

    public function firstSectionIdForClass(int $classId): ?int
    {
        $id = EnrollmentSectionRecord::query()
            ->where('class_id', $classId)
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function branchBelongsToSchool(int $branchId, int $schoolId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('id', $branchId)
            ->where('school_id', $schoolId)
            ->exists();
    }

    public function departmentBelongsToSchool(int $departmentId, int $schoolId, ?int $branchId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('id', $departmentId)
            ->where('school_id', $schoolId)
            ->when($branchId !== null, function ($query) use ($branchId): void {
                $query->where(function ($scope) use ($branchId): void {
                    $scope->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->exists();
    }

    public function placementIsFull(int $classId, int $sectionId, int $academicYearId): bool
    {
        $classCapacity = EnrollmentClassRecord::query()->whereKey($classId)->lockForUpdate()->value('capacity');
        $sectionCapacity = EnrollmentSectionRecord::query()->whereKey($sectionId)->lockForUpdate()->value('capacity');

        $active = static fn () => EnrollmentRecord::query()
            ->where('academic_year_id', $academicYearId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->whereNull('effective_to');

        if ($sectionCapacity !== null && $active()->where('section_id', $sectionId)->count() >= (int) $sectionCapacity) {
            return true;
        }

        return $classCapacity !== null && $active()->where('class_id', $classId)->count() >= (int) $classCapacity;
    }
}
