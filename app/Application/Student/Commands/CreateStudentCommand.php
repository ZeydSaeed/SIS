<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;

final readonly class CreateStudentCommand implements Command
{
    public function __construct(
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public int $gender,
        public string $birthDate,
        public ?string $studentCode = null,
        public ?string $nationalId = null,
        public ?string $birthPlace = null,
        public ?string $nationality = null,
        public ?string $idempotencyKey = null,
        public int $schoolId = 0,
    ) {}
}
