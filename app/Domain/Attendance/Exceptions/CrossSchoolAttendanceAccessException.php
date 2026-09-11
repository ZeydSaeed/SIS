<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class CrossSchoolAttendanceAccessException extends SisDomainException
{
    public static function create(): self
    {
        return new self(
            'Cross-school attendance access is denied.',
            'attendance.cross_school_access',
        );
    }
}
