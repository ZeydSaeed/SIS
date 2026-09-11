<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class AttendancePayloadTooLargeException extends SisDomainException
{
    public static function forLimit(int $limit): self
    {
        return new self(
            "Attendance mark payload exceeds hard limit of {$limit} students.",
            'attendance.payload_too_large',
        );
    }
}
