<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class TermResultNoGradesException extends SisDomainException
{
    public static function forIdentity(): self
    {
        return new self('results.term_no_eligible_grades');
    }
}
