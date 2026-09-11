<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

final readonly class GetSectionAttendanceQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $sectionId,
        public string $date,
        public int $academicYearId,
    ) {}
}
