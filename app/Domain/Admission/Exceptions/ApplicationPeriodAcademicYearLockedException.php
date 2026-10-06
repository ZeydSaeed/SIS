<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Applications take their academic year from the period, so a period's year is fixed
 * once the period exists: changing it would silently move every application to another year.
 */
final class ApplicationPeriodAcademicYearLockedException extends SisDomainException
{
    public static function forPeriod(int $periodId): self
    {
        return new self(
            "The academic year of application period {$periodId} cannot be changed.",
            'admission.period_academic_year_locked',
        );
    }
}
