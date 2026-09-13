<?php

namespace App\Domain\Audit\Exceptions;

final class MissingAuditIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for audit register.');
    }
}
