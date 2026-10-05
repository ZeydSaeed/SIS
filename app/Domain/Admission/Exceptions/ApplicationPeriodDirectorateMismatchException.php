<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** A period belongs to a directorate: only that directorate's schools file applications in it. */
final class ApplicationPeriodDirectorateMismatchException extends SisDomainException
{
    public static function forPeriod(int $periodId, int $schoolId): self
    {
        return new self(
            "Application period {$periodId} belongs to another directorate than school {$schoolId}.",
            'admission.period_directorate_mismatch',
        );
    }
}
