<?php

namespace App\Domain\Enrollment\Repositories;

interface EnrollmentPlacementRepositoryInterface
{
    public function studentBelongsToSchool(int $studentId, int $schoolId): bool;

    public function classBelongsToSchool(int $classId, int $schoolId, int $academicYearId): bool;

    public function sectionBelongsToClass(int $sectionId, int $classId): bool;

    public function classIdForSection(int $sectionId): ?int;

    public function firstSectionIdForClass(int $classId): ?int;

    public function branchBelongsToSchool(int $branchId, int $schoolId): bool;

    /** Department of the school; when a branch is given, the department must not belong to another branch. */
    public function departmentBelongsToSchool(int $departmentId, int $schoolId, ?int $branchId): bool;

    /**
     * True when the class or section capacity (if set) is already taken by active enrollments.
     * Locks both rows — call inside the enrollment transaction.
     */
    public function placementIsFull(int $classId, int $sectionId, int $academicYearId): bool;
}
