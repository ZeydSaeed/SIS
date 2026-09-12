<?php

namespace App\Domain\Timetable\Data;

final readonly class ScheduleExceptionSnapshot
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
