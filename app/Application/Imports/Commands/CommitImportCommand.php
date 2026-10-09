<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;

/** «اعتماد الاستيراد»: after the preview, queues the import of the valid rows. */
final readonly class CommitImportCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $batchId,
        public ?int $userId,
        public ?string $idempotencyKey,
    ) {}
}
