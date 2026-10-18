<?php

namespace App\Application\Leave\Support;

use App\Domain\Academic\Data\AcademicYearSnapshot;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use App\Domain\Timetable\Support\TimetableSettings;

/**
 * The school calendar a leave is counted against, read from its owners (never copied): the working week of the
 * timetable settings («إعدادات الجدول») and the holidays in force in the school calendar («الاجازات»).
 */
final class LeaveCalendar
{
    public function __construct(
        private readonly TimetableEngineReadRepositoryInterface $timetable,
        private readonly HolidayRepositoryInterface $holidays,
        private readonly AcademicYearRepositoryInterface $years,
    ) {}

    public function year(int $academicYearId): AcademicYearSnapshot
    {
        return $this->years->findById($academicYearId) ?? throw LeaveRuleViolation::of('year_invalid');
    }

    /** @return list<int> working `day_of_week` values (1 = Sunday … 7 = Saturday) */
    public function workingWeekdays(int $schoolId, int $academicYearId): array
    {
        return ($this->timetable->settings($schoolId, $academicYearId) ?? TimetableSettings::defaults())->days;
    }

    /**
     * Every date covered by a holiday in force for the school (global + its own) in the year.
     *
     * @return array<string, true>
     */
    public function holidayDates(int $schoolId, int $academicYearId): array
    {
        $dates = [];
        foreach ($this->holidays->listVisible($schoolId, $academicYearId) as $holiday) {
            $day = new \DateTimeImmutable(substr($holiday->startDate, 0, 10));
            $end = new \DateTimeImmutable(substr($holiday->endDate, 0, 10));
            for (; $day <= $end; $day = $day->modify('+1 day')) {
                $dates[$day->format('Y-m-d')] = true;
            }
        }

        return $dates;
    }

    /**
     * @return list<array{id: int, name: string, start_date: string, end_date: string, holiday_type: int, global: bool}>
     */
    public function holidaysBetween(int $schoolId, int $academicYearId, string $from, string $to): array
    {
        $out = [];
        foreach ($this->holidays->listVisible($schoolId, $academicYearId) as $holiday) {
            $start = substr($holiday->startDate, 0, 10);
            $end = substr($holiday->endDate, 0, 10);
            if ($start <= $to && $end >= $from) {
                $out[] = ['id' => $holiday->id, 'name' => $holiday->name, 'start_date' => $start, 'end_date' => $end, 'holiday_type' => $holiday->holidayType, 'global' => $holiday->schoolId === null];
            }
        }

        return $out;
    }
}
