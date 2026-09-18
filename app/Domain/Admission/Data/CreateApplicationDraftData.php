<?php

namespace App\Domain\Admission\Data;

final readonly class CreateApplicationDraftData
{
    public function __construct(
        public int $applicationPeriodId,
        public string $applicationNumber,
        public string $firstName,
        public string $lastName,
        public string $birthDate,
        public int $gender,
        public int $gradeLevelId,
        public ?string $nationalId = null,
        public ?int $specializationId = null,
        public ?string $notes = null,
    ) {}
}
