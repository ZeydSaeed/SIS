<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidGradeStateException extends SisDomainException
{
    public static function forReason(string $reason): self
    {
        return new self($reason, 'grades.invalid_state');
    }
}
