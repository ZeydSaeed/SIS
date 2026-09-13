<?php

namespace App\Application\Workflow\Queries;

final readonly class GetApprovalFlowQuery
{
    public function __construct(
        public int $schoolId,
        public int $flowId,
    ) {}
}
