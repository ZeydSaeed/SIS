<?php

namespace App\Domain\Transfers\Exceptions;

final class MissingTransferIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for transfer mutations.');
    }
}
