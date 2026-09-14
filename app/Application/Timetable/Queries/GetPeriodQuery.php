<?php

namespace App\Application\Timetable\Queries;

final readonly class GetPeriodQuery
{
    public function __construct(
        public int $schoolId,
        public int $periodId,
    ) {}
}
