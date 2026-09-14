<?php

namespace App\Application\Transfers\Commands;

use App\Application\Contracts\Command;

final readonly class ReopenTransferRequestCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $transferRequestId,
        public ?string $idempotencyKey,
    ) {}
}
