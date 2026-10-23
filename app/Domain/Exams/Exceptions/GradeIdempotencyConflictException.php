<?php

namespace App\Domain\Exams\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

/** An idempotency key of a grade command was reused for a different request — refused instead of replaying. */
final class GradeIdempotencyConflictException extends SisDomainException
{
    public static function payloadMismatch(string $command): self
    {
        return new self("Idempotency key of {$command} reused with a different request.", 'grades.idempotency_payload_conflict');
    }
}
