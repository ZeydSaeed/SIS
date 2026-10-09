<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;

/** Queued: imports the valid rows of a confirmed batch through the owning contexts' handlers. */
final readonly class ExecuteImportCommitCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $batchId,
        public ?int $userId,
        /** The batch id makes the run idempotent: a batch is parsed / committed once (status gate). */
        public ?string $idempotencyKey = null,
    ) {}
}
