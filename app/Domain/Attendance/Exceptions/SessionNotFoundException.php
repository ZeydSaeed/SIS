<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class SessionNotFoundException extends SisDomainException
{
    public static function forId(int $sessionId): self
    {
        return new self(
            "Attendance session {$sessionId} was not found.",
            'attendance.session_not_found',
        );
    }
}
