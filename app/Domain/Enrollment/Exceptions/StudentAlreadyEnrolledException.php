<?php

namespace App\Domain\Enrollment\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class StudentAlreadyEnrolledException extends SisDomainException
{
    public static function forYear(int $studentId, int $academicYearId): self
    {
        return new self(
            "Student {$studentId} already has an active enrollment for academic year {$academicYearId}.",
            'enrollment.already_enrolled',
        );
    }
}
