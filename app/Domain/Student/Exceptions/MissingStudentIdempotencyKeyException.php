<?php

namespace App\Domain\Student\Exceptions;

use DomainException;

final class MissingStudentIdempotencyKeyException extends DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key header is required.');
    }
}
