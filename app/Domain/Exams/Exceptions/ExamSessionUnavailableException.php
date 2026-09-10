<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ExamSessionUnavailableException extends SisDomainException
{
    public static function cancelled(int $sessionId): self
    {
        return new self(
            "Exam session {$sessionId} is cancelled and cannot accept grades.",
            'grades.session_unavailable',
        );
    }
}
