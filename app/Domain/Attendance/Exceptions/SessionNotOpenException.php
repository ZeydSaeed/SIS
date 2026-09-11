<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class SessionNotOpenException extends SisDomainException
{
    public static function forId(int $sessionId, int $status): self
    {
        return new self(
            "Attendance session {$sessionId} is not OPEN (status={$status}).",
            'attendance.session_not_open',
        );
    }
}
