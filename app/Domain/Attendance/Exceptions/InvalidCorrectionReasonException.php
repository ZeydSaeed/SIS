<?php

namespace App\Domain\Attendance\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class InvalidCorrectionReasonException extends SisDomainException
{
    public static function empty(): self
    {
        return new self(
            'Correction reason is required and must be non-empty.',
            'attendance.invalid_correction_reason',
        );
    }
}
