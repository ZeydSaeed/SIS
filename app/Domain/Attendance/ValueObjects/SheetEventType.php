<?php

namespace App\Domain\Attendance\ValueObjects;

/** attendance.sheet_events.event_type */
enum SheetEventType: int
{
    case Opened = 1;
    case DraftSaved = 2;
    case Submitted = 3;
    case ReviewStarted = 4;
    case Approved = 5;
    case Returned = 6;
    case Rejected = 7;
    case Corrected = 8;
    case Resubmitted = 9;
}
