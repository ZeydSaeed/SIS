<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EnrollmentClassRecord;
use App\Infrastructure\Persistence\Eloquent\EnrollmentSectionRecord;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;

final class EloquentEnrollmentPlacementRepository implements EnrollmentPlacementRepositoryInterface
{
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
}
