<?php

namespace App\Application\Timetable\Queries;

final readonly class GetTimetableEngineQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
