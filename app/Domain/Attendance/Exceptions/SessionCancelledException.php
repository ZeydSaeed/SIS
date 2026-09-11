<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class SessionCancelledException extends SisDomainException
{
    public static function forId(int $sessionId): self
    {
        return new self(
            "Attendance session {$sessionId} is CANCELLED.",
            'attendance.session_cancelled',
        );
    }
}
