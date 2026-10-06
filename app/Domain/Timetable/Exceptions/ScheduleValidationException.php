<?php

namespace App\Domain\Timetable\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ScheduleValidationException extends SisDomainException
{
    public static function withReason(string $code): self
    {
        // The reason is also the error code, so web pages flash a translatable key (not «domain.error»).
        return new self($code, $code);
    }
}
