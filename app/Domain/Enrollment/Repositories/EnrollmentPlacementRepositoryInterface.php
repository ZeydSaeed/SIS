<?php

namespace App\Domain\Enrollment\Repositories;

interface EnrollmentPlacementRepositoryInterface
{
    public function studentBelongsToSchool(int $studentId, int $schoolId): bool;

    public function classBelongsToSchool(int $classId, int $schoolId, int $academicYearId): bool;

    public function sectionBelongsToClass(int $sectionId, int $classId): bool;

    public function classIdForSection(int $sectionId): ?int;

    public function firstSectionIdForClass(int $classId): ?int;
}
