<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;

final readonly class GetLeaveCoverageQuery implements Query
{
    /** Working days read in one call (each day reads the effective timetable once). */
    public const DAYS_PER_PAGE = 10;

    public function __construct(
        public int $schoolId,
        public int $requestId,
        public LeaveViewer $viewer,
        public int $page = 1,
    ) {}
}
