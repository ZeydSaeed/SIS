<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class EnrollmentDateOutsideYearException extends SisDomainException
{
    public static function forDate(string $effectiveFrom, string $yearStart, string $yearEnd): self
    {
        return new self(
            "Enrollment start {$effectiveFrom} is outside the academic year ({$yearStart} – {$yearEnd}).",
            'enrollment.date_outside_year',
        );
    }
}
