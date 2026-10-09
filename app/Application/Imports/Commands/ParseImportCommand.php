<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;

/** Queued: parse + validate an uploaded batch (nothing is written outside the batch rows). */
final readonly class ParseImportCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $batchId,
        /** The batch id makes the run idempotent: a batch is parsed / committed once (status gate). */
        public ?string $idempotencyKey = null,
    ) {}
}
