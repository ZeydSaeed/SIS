<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class SessionCancelConflictException extends SisDomainException
{
    public static function forId(int $sessionId): self
    {
        return new self(
            "Attendance session {$sessionId} could not be cancelled (already CANCELLED or not OPEN/CLOSED).",
            'attendance.session_cancel_conflict',
        );
    }
}
