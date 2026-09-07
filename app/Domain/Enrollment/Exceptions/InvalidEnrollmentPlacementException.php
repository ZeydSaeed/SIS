<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidEnrollmentPlacementException extends SisDomainException
{
    public static function forReason(string $reason): self
    {
        return new self($reason, 'enrollment.invalid_placement');
    }
}
