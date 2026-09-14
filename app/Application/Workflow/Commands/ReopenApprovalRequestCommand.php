<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;

final readonly class ReopenApprovalRequestCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $requestId,
        public ?string $idempotencyKey,
    ) {}
}
