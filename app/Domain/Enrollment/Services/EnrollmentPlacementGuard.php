<?php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Exceptions\InvalidEnrollmentPlacementException;
use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;

/**
 * Enroll-student placement preconditions — keeps the handler within ARCH-103.
 * Student, class, section, branch and department must all belong to the school
 * (class also to the academic year; section to the class).
 */
final class EnrollmentPlacementGuard
{
    public function __construct(
        private readonly EnrollmentPlacementRepositoryInterface $placement,
    ) {}

    public function assertValid(
        int $schoolId,
        int $academicYearId,
        int $studentId,
        int $classId,
        int $sectionId,
        ?int $branchId,
        ?int $departmentId,
    ): void {
        $reason = $this->rejectionReason(
            $schoolId,
            $academicYearId,
            $studentId,
            $classId,
            $sectionId,
            $branchId,
            $departmentId,
        );

        if ($reason !== null) {
            throw InvalidEnrollmentPlacementException::forReason($reason);
        }
    }

    private function rejectionReason(
        int $schoolId,
        int $academicYearId,
        int $studentId,
        int $classId,
        int $sectionId,
        ?int $branchId,
        ?int $departmentId,
    ): ?string {
        if (! $this->placement->studentBelongsToSchool($studentId, $schoolId)) {
            return 'Student does not belong to the requested school.';
        }

        if (! $this->placement->classBelongsToSchool($classId, $schoolId, $academicYearId)) {
            return 'Class does not belong to the requested school and academic year.';
        }

        if (! $this->placement->sectionBelongsToClass($sectionId, $classId)) {
            return 'Section does not belong to the requested class.';
        }

        if ($branchId !== null && ! $this->placement->branchBelongsToSchool($branchId, $schoolId)) {
            return 'Branch does not belong to the requested school.';
        }

        if ($departmentId !== null
            && ! $this->placement->departmentBelongsToSchool($departmentId, $schoolId, $branchId)) {
            return 'Department does not belong to the requested school or branch.';
        }

        return null;
    }
}
