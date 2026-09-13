<?php

namespace App\Domain\Enrollment\Exceptions;

final class MissingEnrollmentIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for enrollment write commands.');
    }
}
