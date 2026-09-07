<?php

namespace App\Domain\Student\Data;

final readonly class CreateStudentData
{
    public function __construct(
        public string $studentCode,
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public string $fullName,
        public int $gender,
        public string $birthDate,
        public ?string $nationalId = null,
        public ?string $birthPlace = null,
        public ?string $nationality = null,
        public int $status = 1,
    ) {}
}
