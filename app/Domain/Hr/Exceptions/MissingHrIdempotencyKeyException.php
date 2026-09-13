<?php

namespace App\Domain\Hr\Exceptions;

final class MissingHrIdempotencyKeyException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('X-Idempotency-Key header is required for HR write operations.');
    }
}
