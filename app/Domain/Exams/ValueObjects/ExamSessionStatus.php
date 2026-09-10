<?php

namespace App\Domain\Exams\ValueObjects;

/** exams.exam_sessions.status */
enum ExamSessionStatus: int
{
    case Scheduled = 1;
    case InProgress = 2;
    case Completed = 3;
    case Cancelled = 4;

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled => true,
            default => false,
        };
    }
}
