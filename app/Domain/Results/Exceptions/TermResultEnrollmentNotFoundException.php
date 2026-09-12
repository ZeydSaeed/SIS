<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class TermResultEnrollmentNotFoundException extends SisDomainException
{
    public static function forId(int $enrollmentId): self
    {
        return new self("results.enrollment_not_found:{$enrollmentId}");
    }
}
