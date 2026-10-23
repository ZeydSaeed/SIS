<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ExamCompletionBlockedException extends SisDomainException
{
    public static function zeroSessions(): self
    {
        return new self('Exam cannot complete with zero sessions (DR-004).', 'exams.completion.zero_sessions');
    }

    public static function hasScheduledSessions(): self
    {
        return new self('Exam cannot complete while Scheduled sessions exist (DR-004).', 'exams.completion.has_scheduled_sessions');
    }

    public static function hasInProgressSessions(): self
    {
        return new self('Exam cannot complete while InProgress sessions exist (DR-004).', 'exams.completion.has_in_progress_sessions');
    }

    public static function incompleteSessions(): self
    {
        return new self('Exam cannot complete until every non-cancelled session is Completed (DR-004).', 'exams.completion.incomplete_sessions');
    }
}
