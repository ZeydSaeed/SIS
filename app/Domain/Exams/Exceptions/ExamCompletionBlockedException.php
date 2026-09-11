<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class ExamCompletionBlockedException extends DomainException
{
    public static function zeroSessions(): self
    {
        return new self('Exam cannot complete with zero sessions (DR-004).');
    }

    public static function hasScheduledSessions(): self
    {
        return new self('Exam cannot complete while Scheduled sessions exist (DR-004).');
    }

    public static function hasInProgressSessions(): self
    {
        return new self('Exam cannot complete while InProgress sessions exist (DR-004).');
    }

    public static function incompleteSessions(): self
    {
        return new self('Exam cannot complete until every non-cancelled session is Completed (DR-004).');
    }
}
