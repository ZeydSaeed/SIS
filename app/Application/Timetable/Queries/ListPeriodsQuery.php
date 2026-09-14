<?php

namespace App\Application\Timetable\Queries;

final readonly class ListPeriodsQuery
{
    public function __construct(public int $schoolId) {}
}
