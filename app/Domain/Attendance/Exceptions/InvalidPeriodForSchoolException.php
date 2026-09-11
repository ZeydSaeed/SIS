<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidPeriodForSchoolException extends SisDomainException
{
    public static function forPeriod(int $periodId, int $schoolId): self
    {
        return new self(
            "Period {$periodId} does not belong to school {$schoolId}.",
            'attendance.invalid_period_for_school',
        );
    }
}
