<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

/** «لوحة الحضور»: the whole school's lessons of one day (periods × sections) with each sheet's state. */
final readonly class GetAttendanceDayBoardQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?string $date,
    ) {}
}
