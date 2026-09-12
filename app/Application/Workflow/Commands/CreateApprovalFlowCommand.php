<?php

namespace App\Application\Workflow\Commands;

use App\Application\Contracts\Command;

final readonly class CreateApprovalFlowCommand implements Command
{
    /**
     * @param  list<array{step:int|string, role:string}>  $steps
     */
    public function __construct(
        public int $schoolId,
        public string $entityType,
        public string $name,
        public array $steps,
        public bool $isActive = true,
        public ?string $idempotencyKey = null,
    ) {}
}
