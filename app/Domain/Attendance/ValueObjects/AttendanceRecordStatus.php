<?php

namespace App\Domain\Attendance\ValueObjects;

/**
 * attendance.records.status — 0=unmarked (draft only), 1=present, 2=absent, 3=late, 4=excused absence, 5=left early.
 * «غير محدد» is stored only while a sheet is a draft; a submitted sheet never keeps an unmarked row.
 */
enum AttendanceRecordStatus: int
{
    case Unmarked = 0;
    case Present = 1;
    case Absent = 2;
    case Late = 3;
    case Excused = 4;
    case LeftEarly = 5;

    public static function tryFromInt(int $value): ?self
    {
        return self::tryFrom($value);
    }

    /** A decided status (what legacy marking / correction accept). */
    public static function isValid(int $value): bool
    {
        $status = self::tryFrom($value);

        return $status !== null && $status !== self::Unmarked;
    }

    /** Any status a draft may hold, unmarked included. */
    public static function isStorable(int $value): bool
    {
        return self::tryFrom($value) !== null;
    }

    /** The student was in the lesson (fully or partly). */
    public function attended(): bool
    {
        return $this === self::Present || $this === self::Late || $this === self::LeftEarly;
    }

    public function isAbsence(): bool
    {
        return $this === self::Absent || $this === self::Excused;
    }
}
