<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ApplicationPeriodNotFoundException extends SisDomainException
{
    public static function forId(int $id): self
    {
        return new self("Admission application period {$id} was not found.", 'admission.period_missing');
    }
}
