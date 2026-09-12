<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

final readonly class CreateScheduleExceptionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $scheduleId,
        public string $exceptionDate,
        public string $idempotencyKey,
        public ?int $substituteTeacherId = null,
        public ?int $substituteRoomId = null,
        public ?string $reason = null,
        public ?int $createdBy = null,
        public ?string $correlationId = null,
    ) {}
}
