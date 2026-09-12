<?php

namespace App\Domain\Timetable\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ScheduleExceptionNotFoundException extends SisDomainException
{
    public static function forId(int $exceptionId): self
    {
        return new self('timetable.schedule_exception_not_found:'.$exceptionId);
    }
}
