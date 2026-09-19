<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class BulkTransitionApplicationStatusCommand implements Command
{
    /**
     * @param  list<int>  $applicationIds
     */
    public function __construct(
        public int $schoolId,
        public array $applicationIds,
        public int $toStatus,
        public ?int $reviewedBy = null,
        public ?string $notes = null,
        public ?string $idempotencyKey = null,
    ) {}
}
