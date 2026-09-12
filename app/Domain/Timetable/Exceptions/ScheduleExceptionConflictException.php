<?php

namespace App\Domain\Timetable\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ScheduleExceptionConflictException extends SisDomainException
{
    public static function forDate(): self
    {
        return new self('timetable.schedule_exception_already_exists');
    }
}
