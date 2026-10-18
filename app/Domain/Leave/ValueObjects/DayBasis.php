<?php

namespace App\Domain\Leave\ValueObjects;

/**
 * leave.policies.day_basis — how a range of days is counted: school working days (the timetable week, minus the
 * holidays in the school calendar) or every calendar day.
 */
enum DayBasis: int
{
    case WorkingDays = 1;
    case CalendarDays = 2;
}
