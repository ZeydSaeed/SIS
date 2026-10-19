<?php

namespace App\Domain\Leave\ValueObjects;

/** leave.notifications.kind — what the recipient is told (the text is composed in the UI from this). */
enum LeaveNotificationKind: int
{
    /** A request waits for the recipient's decision (new, or the next approval level). */
    case AwaitingApproval = 1;
    case Approved = 2;
    case Rejected = 3;
    case InfoRequested = 4;
    case Cancelled = 5;
    /** One approval level passed; the request waits for the next level (told to the requester). */
    case LevelApproved = 6;
}
