<?php

namespace App\Domain\Leave\ValueObjects;

/** What a leave type is counted in: whole days over a date range, or hours within one day. */
enum LeaveUnit: int
{
    case Days = 1;
    case Hours = 2;
}
