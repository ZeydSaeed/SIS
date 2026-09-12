<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class GpaOfficialAnnualMissingException extends SisDomainException
{
    public static function forIdentity(): self
    {
        return new self('results.gpa_official_annual_missing');
    }
}
