<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class ExamNotFoundException extends DomainException
{
    public static function forId(int $examId): self
    {
        return new self("Exam {$examId} was not found in the current school.");
    }
}
