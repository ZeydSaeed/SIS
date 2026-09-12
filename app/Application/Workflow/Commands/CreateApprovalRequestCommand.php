<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;

final readonly class CreateApprovalRequestCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $flowId,
        public string $entityType,
        public int $entityId,
        public ?int $requestedBy = null,
        public ?string $idempotencyKey = null,
    ) {}
}
