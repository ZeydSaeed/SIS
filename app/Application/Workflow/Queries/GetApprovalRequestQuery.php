<?php

namespace App\Application\Workflow\Queries;

final readonly class GetApprovalRequestQuery
{
    public function __construct(
        public int $schoolId,
        public int $requestId,
    ) {}
}
