<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

final readonly class CreatePeriodCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $periodNumber,
        public string $startTime,
        public string $endTime,
        public int $periodType,
        public string $idempotencyKey,
    ) {}
}
