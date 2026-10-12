<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

/**
 * Work attendance of the school's teachers on a date. With $selfTeacherId only that teacher's own row and recent
 * history are returned (a teacher's own view).
 */
final readonly class GetStaffAttendanceDayQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?string $date,
        public ?int $selfTeacherId = null,
        public bool $canManage = false,
    ) {}
}
