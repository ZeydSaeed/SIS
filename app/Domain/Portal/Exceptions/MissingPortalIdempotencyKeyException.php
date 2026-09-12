<?php

namespace App\Domain\Portal\Exceptions;

final class MissingPortalIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for portal scope link.');
    }
}
