<?php

namespace App\Domain\Teachers\Exceptions;

final class MissingTeacherIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for RegisterTeacher.');
    }
}
