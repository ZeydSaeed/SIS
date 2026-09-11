<?php

namespace App\Domain\Attendance\ValueObjects;

/** attendance.records.status — 1=present, 2=absent, 3=late */
enum AttendanceRecordStatus: int
{
    case Present = 1;
    case Absent = 2;
    case Late = 3;

    public static function tryFromInt(int $value): ?self
    {
        return self::tryFrom($value);
    }

    public static function isValid(int $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
