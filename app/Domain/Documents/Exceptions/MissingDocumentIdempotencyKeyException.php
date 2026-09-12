<?php

namespace App\Domain\Documents\Exceptions;

final class MissingDocumentIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for document mutations.');
    }
}
