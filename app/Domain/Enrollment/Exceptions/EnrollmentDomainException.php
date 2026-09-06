<?php

namespace App\Domain\Enrollment\Exceptions;

final class EnrollmentDomainException extends \DomainException
{
    /**
     * @param  list<string>  $reasons
     */
    public static function notEligible(array $reasons): self
    {
        return new self('Student is not eligible for enrollment: '.implode('; ', $reasons));
    }

    public static function alreadyEnrolled(int $studentId, int $academicYearId): self
    {
        return new self("Student {$studentId} already has an active enrollment for academic year {$academicYearId}.");
    }

    public static function studentNotFound(int $studentId): self
    {
        return new self("Student {$studentId} was not found.");
    }
}
