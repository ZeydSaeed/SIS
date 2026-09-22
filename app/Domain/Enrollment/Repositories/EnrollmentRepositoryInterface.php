<?php

namespace App\Domain\Enrollment\Repositories;

use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;

interface EnrollmentRepositoryInterface
{
    public function hasActiveEnrollment(int $studentId, int $academicYearId): bool;

    public function findById(int $enrollmentId): ?EnrollmentSnapshot;

    public function findByIdAndSchool(int $enrollmentId, int $schoolId): ?EnrollmentSnapshot;

    public function save(CreateEnrollmentData $data): int;

    public function updatePlacement(
        int $enrollmentId,
        int $classId,
        int $sectionId,
        ?int $specializationId,
        ?int $branchId = null,
        ?int $departmentId = null,
    ): void;

    public function cancel(int $enrollmentId, string $effectiveTo): void;

    public function deactivate(int $enrollmentId, string $effectiveTo): void;

    public function reopen(int $enrollmentId): bool;

    public function closeAsTransferred(int $enrollmentId, int $schoolId, string $effectiveTo): bool;

    public function generateEnrollmentNumber(int $schoolId, int $academicYearId): string;
}
