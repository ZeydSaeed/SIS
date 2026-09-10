<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class AcademicEnrollmentInactiveException extends SisDomainException
{
    public static function forId(int $enrollmentId): self
    {
        return new self(
            "Academic enrollment {$enrollmentId} is not active for grading.",
            'grades.enrollment_inactive',
        );
    }
}
