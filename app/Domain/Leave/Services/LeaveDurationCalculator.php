<?php

namespace App\Domain\Leave\Services;

use App\Domain\Leave\ValueObjects\DayBasis;

/**
 * How long a leave is. Working days = the school's working weekdays (the timetable week) that are not holidays of
 * the school calendar; calendar days = every day of the range. Pure: the caller passes the week and the holidays.
 */
final class LeaveDurationCalculator
{
    /** The longest range a request may cover (the table CHECK allows one year). */
    public const MAX_RANGE_DAYS = 366;

    /**
     * @param  list<int>  $workingWeekdays  1 = Sunday … 7 = Saturday (timetable `day_of_week`)
     * @param  array<string, true>  $holidays  'Y-m-d' => true
     */
    public static function days(string $from, string $to, DayBasis $basis, array $workingWeekdays, array $holidays): int
    {
        $count = 0;
        foreach (self::dates($from, $to) as $date) {
            if ($basis === DayBasis::CalendarDays || self::isWorkingDay($date, $workingWeekdays, $holidays)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * The working dates of a range (for the lessons a teacher's leave touches).
     *
     * @param  list<int>  $workingWeekdays
     * @param  array<string, true>  $holidays
     * @return list<string>
     */
    public static function workingDates(string $from, string $to, array $workingWeekdays, array $holidays): array
    {
        return array_values(array_filter(
            self::dates($from, $to),
            static fn (string $date): bool => self::isWorkingDay($date, $workingWeekdays, $holidays),
        ));
    }

    /**
     * @param  list<int>  $workingWeekdays
     * @param  array<string, true>  $holidays
     */
    public static function isWorkingDay(string $date, array $workingWeekdays, array $holidays): bool
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $day !== false
            && in_array((int) $day->format('w') + 1, $workingWeekdays, true)
            && ! isset($holidays[$date]);
    }

    /** @return list<string> every date of the range, both ends included; empty for an invalid or reversed range */
    public static function dates(string $from, string $to): array
    {
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if ($start === false || $end === false || $end < $start || $start->diff($end)->days > self::MAX_RANGE_DAYS) {
            return [];
        }
        $out = [];
        for ($day = $start; $day <= $end; $day = $day->modify('+1 day')) {
            $out[] = $day->format('Y-m-d');
        }

        return $out;
    }

    public static function isDate(string $value): bool
    {
        $day = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $day !== false && $day->format('Y-m-d') === $value;
    }
}
