<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class EnrollmentNotActiveException extends SisDomainException
{
    public static function forId(int $enrollmentId): self
    {
        return new self(
            "Enrollment {$enrollmentId} is not active and cannot be modified.",
            'enrollment.not_active',
        );
    }
}
