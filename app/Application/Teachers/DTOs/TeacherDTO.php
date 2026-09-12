<?php

namespace App\Application\Teachers\DTOs;

final readonly class TeacherDTO
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public string $employeeCode,
        public ?string $nationalId,
        public string $firstName,
        public string $lastName,
        public string $fullName,
        public ?string $specializationField,
        public ?string $hireDate,
        public int $status,
        public int $schoolId,
        public int $academicYearId,
        public bool $isPrimary,
    ) {}
}
