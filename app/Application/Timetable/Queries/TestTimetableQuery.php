<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;

/** «اختبار الجدول»: the full pre-generation test report of a school-year (read-only). */
final readonly class TestTimetableQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
