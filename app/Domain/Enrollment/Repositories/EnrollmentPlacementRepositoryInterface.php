<?php

namespace App\Domain\Enrollment\Repositories;

interface EnrollmentPlacementRepositoryInterface
{
    /** First and last day (Y-m-d) of the academic year, or null when the year does not exist. */
    public function academicYearBounds(int $academicYearId): ?array;

    /** True when an active curriculum exists for the department at the class's grade level in the year. */
    public function hasActiveCurriculum(int $departmentId, int $classId, int $academicYearId): bool;

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
