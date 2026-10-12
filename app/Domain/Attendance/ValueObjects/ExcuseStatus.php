<?php

namespace App\Domain\Attendance\ValueObjects;

/** attendance.excuses.status — submitting an excuse never excuses an absence by itself. */
enum ExcuseStatus: int
{
    case Pending = 1;
    case Approved = 2;
    case Rejected = 3;
    case Withdrawn = 4;
}
