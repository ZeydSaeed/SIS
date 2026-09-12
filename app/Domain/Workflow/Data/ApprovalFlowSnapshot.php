<?php

namespace App\Domain\Workflow\Data;

final readonly class ApprovalFlowSnapshot
{
    /**
     * @param  list<array{step:int, role:string}>  $steps
     */
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $entityType,
        public string $name,
        public array $steps,
        public bool $isActive,
        public string $createdAt,
    ) {}
}
