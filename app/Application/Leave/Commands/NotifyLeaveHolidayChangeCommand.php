<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;

/** Reaction to the outbox event `HolidayChanged`: tell the leave managers which open requests the holiday touches. */
final readonly class NotifyLeaveHolidayChangeCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $holidayId,
    ) {}
}
