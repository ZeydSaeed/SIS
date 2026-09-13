<?php

namespace App\Domain\Hr\Data;

final readonly class EmployeeSnapshot
{
    public function __construct(
        public int $id,
        public string $employeeNumber,
        public ?int $userId,
        public ?int $teacherId,
        public ?string $nationalId,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?string $hireDate,
        public int $status,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public string $createdAt,
        public string $updatedAt,
        public ?int $jobPositionId = null,
        public ?int $academicYearId = null,
    ) {}
}
