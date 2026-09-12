<?php

namespace App\Domain\Promotion\Exceptions;

final class MissingPromotionIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for promotion mutations.');
    }
}
