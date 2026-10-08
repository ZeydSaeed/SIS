<?php

namespace App\Domain\Teachers\ValueObjects;

/**
 * Personal lesson limits of a teacher in a school/year (teachers.teacher_schools): weekly minimum and maximum and
 * the daily maximum. A null limit means "no personal limit" (the timetable's school-wide daily limit applies).
 * Mirrors the CHECK constraints of the table.
 */
final class TeacherWorkloadLimits
{
    public const WEEKLY_MAX = 60;

    public const DAILY_MAX = 12;

    /** Same as {@see self::error()} for an update that may not touch the limits at all (a year is then required). */
    public static function errorForUpdate(bool $updating, ?int $academicYearId, ?int $weeklyMin, ?int $weeklyMax, ?int $dailyMax): ?string
    {
        if (! $updating) {
            return null;
        }

        return $academicYearId === null ? 'teachers.workload_year_required' : self::error($weeklyMin, $weeklyMax, $dailyMax);
    }

    /** Error code of the first broken rule, or null when the limits are coherent. */
    public static function error(?int $weeklyMin, ?int $weeklyMax, ?int $dailyMax): ?string
    {
        if ($weeklyMax !== null && ($weeklyMax < 1 || $weeklyMax > self::WEEKLY_MAX)) {
            return 'teachers.workload_weekly_max_invalid';
        }
        if ($weeklyMin !== null && ($weeklyMin < 0 || $weeklyMin > self::WEEKLY_MAX)) {
            return 'teachers.workload_weekly_min_invalid';
        }
        if ($dailyMax !== null && ($dailyMax < 1 || $dailyMax > self::DAILY_MAX)) {
            return 'teachers.workload_daily_max_invalid';
        }
        if ($weeklyMin !== null && $weeklyMax !== null && $weeklyMin > $weeklyMax) {
            return 'teachers.workload_min_above_max';
        }
        if ($dailyMax !== null && $weeklyMax !== null && $dailyMax > $weeklyMax) {
            return 'teachers.workload_daily_above_weekly';
        }

        return null;
    }
}
