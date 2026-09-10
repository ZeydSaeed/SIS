<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class CurrentGradeAlreadyExistsException extends SisDomainException
{
    public static function forExamEnrollment(int $examEnrollmentId): self
    {
        return new self(
            "A current grade already exists for exam enrollment {$examEnrollmentId}.",
            'grades.conflict',
        );
    }
}
