<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

final readonly class CancelScheduleCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $scheduleId,
        public string $idempotencyKey,
        public ?int $cancelledBy = null,
        public ?string $correlationId = null,
    ) {}
}
