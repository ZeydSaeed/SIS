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
        public ?string $idempotencyKey = null,
    ) {}
}
