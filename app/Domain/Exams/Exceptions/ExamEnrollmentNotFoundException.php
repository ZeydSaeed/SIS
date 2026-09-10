<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ExamEnrollmentNotFoundException extends SisDomainException
{
    public static function forId(int $examEnrollmentId): self
    {
        return new self(
            "Exam enrollment {$examEnrollmentId} was not found.",
            'grades.exam_enrollment_not_found',
        );
    }
}
