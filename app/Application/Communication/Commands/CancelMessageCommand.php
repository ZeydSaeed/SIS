<?php

namespace App\Application\Communication\Commands;

use App\Application\Contracts\Command;

final readonly class CancelMessageCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $messageId,
        public ?string $idempotencyKey,
    ) {}
}
