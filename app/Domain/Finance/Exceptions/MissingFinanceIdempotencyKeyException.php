<?php

namespace App\Domain\Finance\Exceptions;

final class MissingFinanceIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for finance mutations.');
    }
}
