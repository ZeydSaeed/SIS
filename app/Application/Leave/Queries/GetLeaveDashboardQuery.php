<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;

final readonly class GetLeaveDashboardQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $today,
        public LeaveViewer $viewer,
    ) {}
}
