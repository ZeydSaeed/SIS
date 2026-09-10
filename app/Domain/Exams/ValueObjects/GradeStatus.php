<?php

namespace App\Domain\Exams\ValueObjects;

/** exams.student_grades.status */
enum GradeStatus: int
{
    case Draft = 1;
    case Entered = 2;
    case Submitted = 3;
    case Finalized = 4;
    case Voided = 5;

    public function allowsScoreMutation(): bool
    {
        return match ($this) {
            self::Draft, self::Entered, self::Submitted => true,
            self::Finalized, self::Voided => false,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Voided;
    }
}
