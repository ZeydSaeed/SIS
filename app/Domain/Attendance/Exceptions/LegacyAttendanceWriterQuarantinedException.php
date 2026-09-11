<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * R1.9 Option B — legacy Attendance batch writer is quarantined.
 * CQRS Application/Attendance handlers are the only supported write path.
 */
final class LegacyAttendanceWriterQuarantinedException extends SisDomainException
{
    public static function forMethod(string $method): self
    {
        return new self(
            "Legacy Attendance writer method {$method} is quarantined. "
            .'Use Application/Attendance CQRS commands (e.g. MarkSectionAttendance). '
            .'Full class deletion requires separate authorization.',
            'attendance.legacy_writer_quarantined',
        );
    }
}
