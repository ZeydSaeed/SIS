<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class SessionCloseConflictException extends SisDomainException
{
    public static function forId(int $sessionId): self
    {
        return new self(
            "Attendance session {$sessionId} could not be closed (already closed or not OPEN).",
            'attendance.session_close_conflict',
        );
    }
}
