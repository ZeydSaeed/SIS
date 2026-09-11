<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class AcademicYearDateOutOfBoundsException extends SisDomainException
{
    public static function forDate(int $academicYearId, string $sessionDate): self
    {
        return new self(
            "Session date {$sessionDate} is outside academic year {$academicYearId} bounds.",
            'attendance.academic_year_date_out_of_bounds',
        );
    }
}
