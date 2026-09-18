<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class CreateApplicationDraftCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $applicationPeriodId,
        public string $firstName,
        public string $lastName,
        public string $birthDate,
        public int $gender,
        public int $gradeLevelId,
        public ?string $nationalId = null,
        public ?int $specializationId = null,
        public ?string $notes = null,
        public ?string $idempotencyKey = null,
    ) {}
}
