<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;

final readonly class GetLeaveRequestQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $requestId,
        public LeaveViewer $viewer,
    ) {}
}
