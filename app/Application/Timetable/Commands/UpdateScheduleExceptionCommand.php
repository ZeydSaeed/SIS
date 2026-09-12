<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

final readonly class UpdateScheduleExceptionCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $exceptionId,
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
