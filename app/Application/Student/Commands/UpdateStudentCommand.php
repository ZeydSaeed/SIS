<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateStudentCommand implements Command
{
    public function __construct(
        public int $studentId,
        public string $firstName,
        public ?string $middleName,
        public string $lastName,
        public int $gender,
        public string $birthDate,
        public ?string $nationalId = null,
        public ?string $birthPlace = null,
        public ?string $nationality = null,
    ) {}
}
