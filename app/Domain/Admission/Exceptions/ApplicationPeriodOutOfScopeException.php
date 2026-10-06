<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** A period of a directorate the user is not linked to cannot be edited or re-statused. */
final class ApplicationPeriodOutOfScopeException extends SisDomainException
{
    public static function forPeriod(int $periodId): self
    {
        return new self(
            "Application period {$periodId} belongs to a directorate outside the user's scope.",
            'admission.period_out_of_scope',
        );
    }
}
