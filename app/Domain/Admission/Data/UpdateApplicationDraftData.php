<?php

namespace App\Domain\Admission\Data;

final readonly class UpdateApplicationDraftData
{
    public function __construct(
        public int $applicationId,
        public ?string $notes,
        public ?string $reviewedAt,
    ) {}
}
