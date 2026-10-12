<?php

namespace App\Domain\Attendance\ValueObjects;

/** attendance.sessions.origin — how the session came to exist. */
enum SessionOrigin: int
{
    /** Created by hand (legacy «إنشاء جلسة» / API). */
    case Manual = 1;
    /** Opened from the effective timetable lesson of that date. */
    case Timetable = 2;
    /** An exceptional lesson outside the timetable, opened by an authorised reviewer. */
    case Exceptional = 3;
}
