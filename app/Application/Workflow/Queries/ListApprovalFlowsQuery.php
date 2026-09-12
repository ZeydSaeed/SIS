<?php

namespace App\Application\Workflow\Queries;

final readonly class ListApprovalFlowsQuery
{
    public function __construct(
        public int $schoolId,
        public ?string $entityType = null,
        public ?bool $activeOnly = null,
    ) {}
}
