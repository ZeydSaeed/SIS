<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidAttendanceStatusException extends SisDomainException
{
    public static function forValue(int $status): self
    {
        return new self(
            "Invalid attendance status {$status}; expected 1 (present), 2 (absent), or 3 (late).",
            'attendance.invalid_status',
        );
    }
}
