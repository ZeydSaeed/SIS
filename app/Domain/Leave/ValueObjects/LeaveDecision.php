<?php

namespace App\Domain\Leave\ValueObjects;

/** What an approver decides on a pending request. */
enum LeaveDecision: string
{
    case Approve = 'approve';
    case Reject = 'reject';
    case RequestInfo = 'info';

    public function requiresNote(): bool
    {
        return $this !== self::Approve;
    }
}
