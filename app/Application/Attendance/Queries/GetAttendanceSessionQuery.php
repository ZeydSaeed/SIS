<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

final readonly class GetAttendanceSessionQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $sessionId,
        public bool $includeRecords = false,
    ) {}
}
