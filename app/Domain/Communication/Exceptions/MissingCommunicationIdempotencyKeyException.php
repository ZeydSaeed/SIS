<?php

namespace App\Domain\Communication\Exceptions;

final class MissingCommunicationIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for communication mutations.');
    }
}
