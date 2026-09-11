<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class EmptyAttendancePayloadException extends SisDomainException
{
    public static function create(): self
    {
        return new self(
            'Attendance mark payload must not be empty.',
            'attendance.empty_payload',
        );
    }
}
