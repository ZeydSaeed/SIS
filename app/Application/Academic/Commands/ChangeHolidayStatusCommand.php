<?php

namespace App\Application\Academic\Commands;

use App\Application\Contracts\Command;

/** «الاجازات»: takes a school holiday out of the calendar (never deleted) or puts it back. */
final readonly class ChangeHolidayStatusCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $holidayId,
        public bool $active,
        public ?string $idempotencyKey,
    ) {}
}
