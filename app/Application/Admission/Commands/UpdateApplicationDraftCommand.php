<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateApplicationDraftCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $applicationId,
        public ?string $notes = null,
        public ?string $reviewedAt = null,
        public ?int $branchId = null,
        public ?string $branchName = null,
        public ?string $departmentName = null,
        public ?int $gradeLevelId = null,
        public ?string $intendedGradeName = null,
        public ?int $specializationId = null,
        public ?string $specializationName = null,
        public bool $updatePlacement = false,
        public ?string $idempotencyKey = null,
    ) {}
}
