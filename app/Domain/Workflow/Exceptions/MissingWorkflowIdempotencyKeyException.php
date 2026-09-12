<?php

namespace App\Domain\Workflow\Exceptions;

final class MissingWorkflowIdempotencyKeyException extends \DomainException
{
    public static function required(): self
    {
        return new self('X-Idempotency-Key is required for workflow mutations.');
    }
}
