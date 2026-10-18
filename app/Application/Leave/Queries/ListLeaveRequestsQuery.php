<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;

final readonly class ListLeaveRequestsQuery implements Query
{
    /**
     * @param  array<string, mixed>  $filters  see LeaveReadRepositoryInterface::requests
     */
    public function __construct(
        public int $schoolId,
        public LeaveViewer $viewer,
        public array $filters,
        public int $page,
        public int $perPage,
    ) {}
}
