<?php

namespace App\Domain\Exams\ValueObjects;

/** exams.exams.status */
enum ExamStatus: int
{
    case Draft = 1;
    case Scheduled = 2;
    case InProgress = 3;
    case Completed = 4;
    case Cancelled = 5;

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled => true,
            default => false,
        };
    }
}
