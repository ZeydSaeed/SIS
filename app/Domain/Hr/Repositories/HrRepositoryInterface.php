<?php

namespace App\Domain\Hr\Repositories;

use App\Domain\Hr\Data\EmployeeSnapshot;
use App\Domain\Hr\Data\JobPositionSnapshot;

interface HrRepositoryInterface
{
    public function jobPositionCodeExists(int $schoolId, string $code): bool;

    public function findJobPosition(int $schoolId, int $jobPositionId): ?JobPositionSnapshot;

    public function createJobPosition(
        int $schoolId,
        string $code,
        string $name,
        int $category,
        int $status,
        string $at,
    ): int;

    /**
     * @return list<JobPositionSnapshot>
     */
    public function listJobPositions(int $schoolId, ?int $status = null): array;

    public function employeeNumberExists(int $schoolId, string $employeeNumber): bool;

    public function teacherLinkExists(int $schoolId, int $teacherId): bool;

    public function teacherBelongsToSchool(int $teacherId, int $schoolId, int $academicYearId): bool;

    public function createEmployee(
        int $schoolId,
        string $employeeNumber,
        ?int $userId,
        ?int $teacherId,
        ?string $nationalId,
        string $firstName,
        string $lastName,
        string $fullName,
        ?string $hireDate,
        int $status,
        string $effectiveFrom,
        string $at,
    ): int;

    public function assignSchool(
        int $employeeId,
        int $schoolId,
        int $academicYearId,
        ?int $jobPositionId,
        bool $isPrimary,
        string $at,
    ): int;

    /**
     * @return list<EmployeeSnapshot>
     */
    public function listEmployeesBySchool(int $schoolId, ?int $academicYearId = null, ?int $status = null): array;
}
