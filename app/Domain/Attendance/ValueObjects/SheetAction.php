<?php

namespace App\Domain\Attendance\ValueObjects;

enum SheetAction: string
{
    case Submit = 'submit';
    case StartReview = 'start_review';
    case Approve = 'approve';
    case Return = 'return';
    case Reject = 'reject';

    /** Return and reject must explain themselves to the teacher. */
    public function requiresReason(): bool
    {
        return $this === self::Return || $this === self::Reject;
    }

    public function eventType(bool $resubmission): SheetEventType
    {
        return match ($this) {
            self::Submit => $resubmission ? SheetEventType::Resubmitted : SheetEventType::Submitted,
            self::StartReview => SheetEventType::ReviewStarted,
            self::Approve => SheetEventType::Approved,
            self::Return => SheetEventType::Returned,
            self::Reject => SheetEventType::Rejected,
        };
    }
}
