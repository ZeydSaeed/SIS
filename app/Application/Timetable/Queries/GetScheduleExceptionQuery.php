<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;

final readonly class GetScheduleExceptionQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $exceptionId,
    ) {}
}
