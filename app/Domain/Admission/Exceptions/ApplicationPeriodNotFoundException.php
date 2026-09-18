<?php

namespace App\Domain\Admission\Exceptions;

use DomainException;

final class ApplicationPeriodNotFoundException extends DomainException
{
    public static function forId(int $id): self
    {
        return new self("Admission application period {$id} was not found.");
    }
}
