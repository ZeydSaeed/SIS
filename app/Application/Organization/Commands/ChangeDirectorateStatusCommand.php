<?php

namespace App\Application\Organization\Commands;

use App\Application\Contracts\Command;

final readonly class ChangeDirectorateStatusCommand implements Command
{
    public function __construct(
        public int $directorateId,
        public int $status,
        public ?string $idempotencyKey,
    ) {}
}
