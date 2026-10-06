<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «زحف المادة»: the lesson (and the block it runs into) moves one period later (+1) or earlier (−1). */
final readonly class ShiftScheduleCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $scheduleId,
        public int $direction,
        public string $idempotencyKey,
    ) {}
}
