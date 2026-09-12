<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;

final readonly class ListSchedulesQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $sectionId = null,
        public ?int $lifecycleStatus = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {}
}
