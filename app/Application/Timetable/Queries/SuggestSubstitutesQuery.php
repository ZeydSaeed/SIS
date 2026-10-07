<?php

namespace App\Application\Timetable\Queries;

final readonly class SuggestSubstitutesQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $scheduleId,
        public string $date,
    ) {}
}
