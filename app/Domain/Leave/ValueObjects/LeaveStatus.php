<?php

namespace App\Domain\Leave\ValueObjects;

/**
 * leave.requests.status. A request is never deleted: it ends as approved, rejected, cancelled (after approval) or
 * withdrawn (before a decision).
 */
enum LeaveStatus: int
{
    case Draft = 1;
    case Pending = 2;
    case InfoRequired = 3;
    case Approved = 4;
    case Rejected = 5;
    case Cancelled = 6;
    case Withdrawn = 7;

    /** Still editable by the requester (a draft, or returned for more information). */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::InfoRequired;
    }

    /** Counts against the person's time and balance (overlap checks, reserved balance). */
    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::InfoRequired, self::Approved], true);
    }

    /** Awaiting a decision or more information — reserves balance until decided. */
    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::InfoRequired;
    }

    /** May still receive attachments. */
    public function acceptsDocuments(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::InfoRequired, self::Approved], true);
    }

    /** @return list<int> */
    public static function activeValues(): array
    {
        return [self::Pending->value, self::InfoRequired->value, self::Approved->value];
    }
}
