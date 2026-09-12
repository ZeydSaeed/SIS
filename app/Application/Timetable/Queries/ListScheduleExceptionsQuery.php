<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;

final readonly class ListScheduleExceptionsQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public ?int $scheduleId = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public int $page = 1,
        public int $perPage = 50,
    ) {}
}
