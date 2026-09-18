<?php

namespace App\Domain\Admission\Exceptions;

use DomainException;

final class ApplicationPeriodCapacityExceededException extends DomainException
{
    public static function forPeriod(int $periodId): self
    {
        return new self("Application period {$periodId} has reached max_applications.");
    }
}
