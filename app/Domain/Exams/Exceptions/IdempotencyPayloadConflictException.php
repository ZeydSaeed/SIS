<?php

namespace App\Domain\Exams\Exceptions;

use DomainException;

final class IdempotencyPayloadConflictException extends DomainException
{
    public static function mismatch(): self
    {
        return new self('Idempotency key reused with a different canonical payload.');
    }
}
