<?php

namespace App\Domain\Vocational\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class VocationalValidationException extends SisDomainException
{
    public static function withReason(string $code): self
    {
        return new self($code);
    }
}
