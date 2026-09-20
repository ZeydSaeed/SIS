<?php

namespace App\Domain\Academic\Services;

final class AcademicYearDateWindow
{
    public static function contains(string $yearStart, string $yearEnd, string $startDate, string $endDate): bool
    {
        $yearStartDay = self::dateOnly($yearStart);
        $yearEndDay = self::dateOnly($yearEnd);
        $startDay = self::dateOnly($startDate);
        $endDay = self::dateOnly($endDate);

        if ($yearStartDay === null || $yearEndDay === null || $startDay === null || $endDay === null) {
            return false;
        }

        return $startDay >= $yearStartDay && $endDay <= $yearEndDay;
    }

    private static function dateOnly(string $value): ?string
    {
        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }
}
