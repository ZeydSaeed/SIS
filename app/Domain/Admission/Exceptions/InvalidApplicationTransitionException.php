<?php

namespace App\Domain\Admission\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidApplicationTransitionException extends SisDomainException
{
    public static function fromTo(int $from, int $to): self
    {
        return new self("Cannot transition application status from {$from} to {$to}.", 'admission.invalid_transition');
    }
}
