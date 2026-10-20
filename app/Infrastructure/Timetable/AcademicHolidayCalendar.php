<?php

namespace App\Infrastructure\Timetable;

use App\Application\Timetable\Contracts\HolidayCalendarInterface;
use Illuminate\Support\Facades\DB;

/** One indexed existence check on `academic.holidays` (global + the school's own, active only) — same rule attendance uses. */
final class AcademicHolidayCalendar implements HolidayCalendarInterface
{
    public function isHoliday(int $schoolId, int $academicYearId, string $date): bool
    {
        return DB::table('academic.holidays')
            ->where('academic_year_id', $academicYearId)
            ->where('status', 1)
            ->where(static fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }
}
