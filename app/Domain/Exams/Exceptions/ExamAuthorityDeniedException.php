<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class ExamAuthorityDeniedException extends DomainException
{
    public static function forAction(string $action): self
    {
        return new self("Exam administration action '{$action}' is not authorized.");
    }

    public static function schoolMismatch(): self
    {
        return new self('School context does not match the commanded school.');
    }

    public static function missingSchoolContext(): self
    {
        return new self('School context is required for exam administration.');
    }
}
