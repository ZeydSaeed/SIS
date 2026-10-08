<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ApplicationPeriodClosedException extends SisDomainException
{
    public static function forPeriod(int $periodId): self
    {
        return new self("Application period {$periodId} is not open for new applications.", 'admission.period_closed');
    }
}
