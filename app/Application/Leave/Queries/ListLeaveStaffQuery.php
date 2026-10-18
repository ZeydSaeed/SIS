<?php

namespace App\Application\Leave\Queries;

use App\Application\Contracts\Query;
use App\Application\Leave\Support\LeaveViewer;

final readonly class ListLeaveStaffQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public LeaveViewer $viewer,
        public bool $canManage,
    ) {}
}
