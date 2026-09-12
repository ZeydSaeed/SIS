<?php

namespace App\Application\Timetable\DTOs;

final readonly class ScheduleExceptionDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $scheduleId,
        public string $exceptionDate,
        public ?int $substituteTeacherId,
        public ?int $substituteRoomId,
        public ?string $reason,
    ) {}
}
