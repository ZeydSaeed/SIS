<?php

namespace App\Application\Transfers\Commands;

use App\Application\Contracts\Command;

final readonly class RejectTransferRequestCommand implements Command
{
    public function __construct(
        public int $toSchoolId,
        public int $transferRequestId,
        public ?int $approvedBy,
        public ?string $idempotencyKey,
    ) {}
}
