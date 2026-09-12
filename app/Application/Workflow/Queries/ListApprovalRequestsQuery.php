<?php

namespace App\Application\Workflow\Queries;

final readonly class ListApprovalRequestsQuery
{
    public function __construct(
        public int $schoolId,
        public ?string $entityType = null,
        public ?int $entityId = null,
        public ?int $requestStatus = null,
    ) {}
}
