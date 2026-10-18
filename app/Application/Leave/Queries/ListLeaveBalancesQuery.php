<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;

final readonly class ListLeaveBalancesQuery implements Query
{
    /**
     * @param  array{kind?: int|null, type_ids?: list<int>, q?: string}  $filters
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public LeaveViewer $viewer,
        public array $filters,
        public int $page,
        public int $perPage,
    ) {}
}
