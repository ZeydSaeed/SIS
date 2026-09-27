<?php

namespace App\Domain\Admission\Data;

final readonly class UpdateApplicationDraftData
{
    public function __construct(
        public int $applicationId,
        public ?string $notes,
        public ?string $reviewedAt,
        public ?int $branchId = null,
        public ?string $branchName = null,
        public ?string $departmentName = null,
        public ?int $gradeLevelId = null,
        public ?string $intendedGradeName = null,
        public ?int $specializationId = null,
        public ?string $specializationName = null,
        public bool $updatePlacement = false,
    ) {}
}
