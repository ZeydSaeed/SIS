<?php

namespace App\Application\Hr\Commands;

use App\Application\Contracts\Command;

final readonly class RegisterEmployeeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $employeeNumber,
        public string $firstName,
        public string $lastName,
        public ?string $nationalId,
        public ?string $hireDate,
        public ?int $jobPositionId,
        public ?int $teacherId,
        public ?int $userId,
        public ?string $idempotencyKey,
    ) {}
}
