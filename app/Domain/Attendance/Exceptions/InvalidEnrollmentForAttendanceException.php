<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidEnrollmentForAttendanceException extends SisDomainException
{
    /**
     * @param  list<string>  $reasons
     */
    public static function withReasons(array $reasons): self
    {
        $detail = $reasons === [] ? 'Enrollment is not valid for attendance.' : implode('; ', $reasons);

        return new self($detail, 'attendance.invalid_enrollment');
    }
}
