<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

final readonly class GetAttendancePolicyQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
