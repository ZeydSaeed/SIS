<?php

namespace App\Domain\Leave\ValueObjects;

/** leave.request_events.event_type — the append-only history of a request. */
enum LeaveEventType: int
{
    case Created = 1;
    case Updated = 2;
    case Submitted = 3;
    case LevelApproved = 4;
    case Approved = 5;
    case Rejected = 6;
    case InfoRequested = 7;
    case Resubmitted = 8;
    case Withdrawn = 9;
    case Cancelled = 10;
    case DocumentAttached = 11;
}
