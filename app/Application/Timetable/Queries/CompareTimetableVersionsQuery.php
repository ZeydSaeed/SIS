<?php

namespace App\Application\Timetable\Queries;

/** Version A vs version B; null = the working grid. */
final readonly class CompareTimetableVersionsQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $versionA,
        public ?int $versionB,
    ) {}
}
