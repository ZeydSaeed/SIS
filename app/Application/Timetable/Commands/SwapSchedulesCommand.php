<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «استبدال حصة بأخرى»: two lessons of one section trade places. */
final readonly class SwapSchedulesCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $firstScheduleId,
        public int $secondScheduleId,
        public string $idempotencyKey,
    ) {}
}
