<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;

final readonly class ReactivateApprovalFlowCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $flowId,
        public ?string $idempotencyKey,
    ) {}
}
