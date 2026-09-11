<?php

namespace App\Domain\Attendance\ValueObjects;

/** attendance.sessions.status */
enum SessionStatus: int
{
    case Open = 1;
    case Closed = 2;
    case Cancelled = 3;

    public function allowsMark(): bool
    {
        return $this === self::Open;
    }

    public function allowsCorrect(): bool
    {
        return $this !== self::Cancelled;
    }

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
