<?php

namespace App\Domain\Timetable\ValueObjects;

/** A teacher / room / section / workshop slot: unavailable (hard), to avoid (soft), preferred (soft). */
enum AvailabilityKind: int
{
    case Unavailable = 1;
    case Avoid = 2;
    case Preferred = 3;
}
