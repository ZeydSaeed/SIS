<?php

namespace App\Domain\Timetable\ValueObjects;

enum ScheduleLifecycleStatus: int
{
    case Active = 1;
    case Cancelled = 2;
}
