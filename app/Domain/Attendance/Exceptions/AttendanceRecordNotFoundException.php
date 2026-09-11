<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class AttendanceRecordNotFoundException extends SisDomainException
{
    public static function forStudent(int $sessionId, int $studentId): self
    {
        return new self(
            "Attendance record for session {$sessionId} and student {$studentId} was not found.",
            'attendance.record_not_found',
        );
    }
}
