<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidCancellationReasonException extends SisDomainException
{
    public static function empty(): self
    {
        return new self(
            'Cancellation reason is required and must be non-empty.',
            'attendance.invalid_cancellation_reason',
        );
    }
}
