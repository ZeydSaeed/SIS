<?php

namespace App\Domain\Exams\ValueObjects;

/** exams.exam_enrollments.status — participation, not grades */
enum ExamEnrollmentStatus: int
{
    case Registered = 1;
    case Confirmed = 2;
    case Present = 3;
    case Absent = 4;
    case Withdrawn = 5;

    public function isActiveSeat(): bool
    {
        return match ($this) {
            self::Registered, self::Confirmed, self::Present => true,
            default => false,
        };
    }
}
