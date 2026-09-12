<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class MissingGradeIdempotencyKeyException extends SisDomainException
{
    public static function required(): self
    {
        return new self(
            'Idempotency key is required for grade mutating commands.',
            'grades.idempotency_key_required',
        );
    }
}
