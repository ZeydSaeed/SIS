<?php

namespace App\Domain\Timetable\ValueObjects;

/** `timetable.periods.period_type` — only lesson periods take schedules. */
enum PeriodType: int
{
    case Lesson = 1;
    case Break = 2;
}
