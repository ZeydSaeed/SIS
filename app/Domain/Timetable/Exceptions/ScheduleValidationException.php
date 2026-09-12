<?php

namespace App\Domain\Timetable\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ScheduleValidationException extends SisDomainException
{
    public static function withReason(string $code): self
    {
        return new self($code);
    }
}
