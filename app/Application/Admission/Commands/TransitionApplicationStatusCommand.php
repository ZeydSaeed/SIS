<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class TransitionApplicationStatusCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $applicationId,
        public int $toStatus,
        public ?int $reviewedBy = null,
        public ?string $notes = null,
        public ?string $idempotencyKey = null,
    ) {}
}
