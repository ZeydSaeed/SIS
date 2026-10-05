<?php

namespace App\Domain\Organization\Exceptions;

final class MissingOrganizationIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for organization write commands.');
    }
}
