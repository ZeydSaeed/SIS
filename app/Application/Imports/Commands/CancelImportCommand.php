<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;

/** «إلغاء»: a previewed (or failed) batch is closed without importing anything — kept for history. */
final readonly class CancelImportCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $batchId,
        public ?string $idempotencyKey,
    ) {}
}
