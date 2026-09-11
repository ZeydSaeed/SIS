<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class IdempotencyPayloadConflictException extends SisDomainException
{
    public static function mismatch(): self
    {
        return new self(
            'Idempotency key reused with a different CancelAttendanceSession payload.',
            'attendance.idempotency_payload_conflict',
        );
    }
}
