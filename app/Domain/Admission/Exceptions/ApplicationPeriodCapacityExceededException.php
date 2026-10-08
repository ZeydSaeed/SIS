<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ApplicationPeriodCapacityExceededException extends SisDomainException
{
    public static function forPeriod(int $periodId): self
    {
        return new self("Application period {$periodId} has reached max_applications.", 'admission.period_capacity_reached');
    }
}
