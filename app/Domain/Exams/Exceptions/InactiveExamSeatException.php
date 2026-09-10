<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InactiveExamSeatException extends SisDomainException
{
    public static function forId(int $examEnrollmentId): self
    {
        return new self(
            "Exam enrollment {$examEnrollmentId} is not an active seat for grading.",
            'grades.seat_inactive',
        );
    }
}
