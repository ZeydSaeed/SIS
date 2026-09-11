<?php

namespace App\Application\Attendance\Queries;

use App\Application\Contracts\Query;

final readonly class ListAttendanceSessionsQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?int $sectionId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?int $status = null,
        public int $page = 1,
        public int $perPage = 25,
    ) {}
}
