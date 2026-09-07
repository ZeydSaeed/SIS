<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class EnrollmentNotFoundException extends SisDomainException
{
    public static function forId(int $enrollmentId): self
    {
        return new self(
            "Enrollment {$enrollmentId} was not found.",
            'enrollment.not_found',
        );
    }
}
