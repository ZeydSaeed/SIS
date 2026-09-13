<?php

namespace App\Application\Hr\DTOs;

final readonly class EmployeeDTO
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
        public ?int $jobPositionId,
        public ?int $academicYearId,
        public string $createdAt,
    ) {}
}
