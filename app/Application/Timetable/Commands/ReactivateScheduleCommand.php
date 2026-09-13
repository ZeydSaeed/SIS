<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

final readonly class ReactivateScheduleCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $scheduleId,
        public string $idempotencyKey,
        public ?int $reactivatedBy = null,
        public ?string $correlationId = null,
    ) {}
}
