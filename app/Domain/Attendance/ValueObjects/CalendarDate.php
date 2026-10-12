<?php

namespace App\Domain\Attendance\ValueObjects;

/**
 * A Y-m-d date that exists in the calendar (2026-02-30 has the right shape but is not a date — PostgreSQL would
 * reject it). Every date taken from a query string goes through this check before it reaches a query.
 */
final class CalendarDate
{
    public static function valid(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1) {
            return false;
        }

        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) && (int) $m[1] >= 1900 && (int) $m[1] <= 2999;
    }
}
