<?php

namespace App\Application\Timetable\Queries;

final readonly class SuggestScheduleMovesQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $scheduleId,
    ) {}
}
