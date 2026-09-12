<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class AnnualResultNoTermResultsException extends SisDomainException
{
    public static function forIdentity(): self
    {
        return new self('results.annual_no_term_results');
    }
}
