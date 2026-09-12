<?php

namespace App\Domain\Results\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class MissingTermResultIdempotencyKeyException extends SisDomainException
{
    public static function required(): self
    {
        return new self('results.idempotency_key_required');
    }
}
