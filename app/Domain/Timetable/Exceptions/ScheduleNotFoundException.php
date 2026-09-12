<?php

namespace App\Domain\Timetable\Exceptions;

use App\Domain\Shared\Exceptions\SisDomainException;

final class ScheduleNotFoundException extends SisDomainException
{
    public static function forId(int $scheduleId): self
    {
        return new self('timetable.schedule_not_found:'.$scheduleId);
    }
}
