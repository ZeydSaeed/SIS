<?php

namespace App\Application\Timetable\Contracts;

/**
 * The timetable's view of the school calendar («الاجازات»): whether a date is closed by a holiday in force.
 * The holidays stay owned by the Academic context; this port only answers the one question the timetable asks.
 */
interface HolidayCalendarInterface
{
    public function isHoliday(int $schoolId, int $academicYearId, string $date): bool;
}
