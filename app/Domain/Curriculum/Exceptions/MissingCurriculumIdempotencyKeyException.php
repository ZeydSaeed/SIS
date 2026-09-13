<?php

namespace App\Domain\Curriculum\Exceptions;

final class MissingCurriculumIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for curriculum write commands.');
    }
}
