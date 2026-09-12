<?php

namespace App\Domain\Vocational\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class MissingVocationalIdempotencyKeyException extends SisDomainException
{
    public static function required(): self
    {
        return new self('vocational.idempotency_key_required');
    }
}
