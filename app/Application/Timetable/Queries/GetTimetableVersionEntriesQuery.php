<?php

namespace App\Application\Timetable\Queries;

final readonly class GetTimetableVersionEntriesQuery
{
    public function __construct(
        public int $schoolId,
        public int $versionId,
    ) {}
}
