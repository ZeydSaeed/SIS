<?php

namespace App\Domain\Admission\Exceptions;

use DomainException;

final class ApplicationPeriodClosedException extends DomainException
{
    public static function forPeriod(int $periodId): self
    {
        return new self("Application period {$periodId} is not open for new applications.");
    }
}
