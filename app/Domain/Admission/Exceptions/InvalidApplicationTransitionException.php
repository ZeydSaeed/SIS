<?php

namespace App\Domain\Admission\Exceptions;

use DomainException;

final class InvalidApplicationTransitionException extends DomainException
{
    public static function fromTo(int $from, int $to): self
    {
        return new self("Cannot transition application status from {$from} to {$to}.");
    }
}
