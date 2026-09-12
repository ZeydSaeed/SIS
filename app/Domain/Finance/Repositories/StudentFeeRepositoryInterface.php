<?php

namespace App\Domain\Finance\Repositories;

use App\Domain\Finance\Data\StudentFeeSnapshot;

interface StudentFeeRepositoryInterface
{
    public function enrollmentBelongsToSchoolYear(int $enrollmentId, int $schoolId, int $academicYearId): bool;

    public function academicYearExists(int $academicYearId): bool;

    public function findActiveFeeTypeAmount(int $schoolId, int $feeTypeId): ?string;

    public function findExistingId(
        int $schoolId,
        int $enrollmentId,
        int $feeTypeId,
        int $academicYearId,
    ): ?int;

    public function create(
        int $schoolId,
        int $enrollmentId,
        int $feeTypeId,
        int $academicYearId,
        string $amount,
        ?string $dueDate,
        int $status,
        string $createdAt,
    ): int;

    public function findById(int $schoolId, int $studentFeeId): ?StudentFeeSnapshot;

    public function updateStatus(int $schoolId, int $studentFeeId, int $status): void;

    public function findStudentIdByEnrollment(int $schoolId, int $enrollmentId): ?int;

    /**
     * @return list<StudentFeeSnapshot>
     */
    public function listBySchool(
        int $schoolId,
        ?int $enrollmentId = null,
        ?int $academicYearId = null,
        ?int $status = null,
    ): array;
}
