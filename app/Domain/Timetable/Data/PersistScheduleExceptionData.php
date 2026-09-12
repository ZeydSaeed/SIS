<?php

namespace App\Domain\Timetable\Data;

final readonly class PersistScheduleExceptionData
{
    public function __construct(
        public int $schoolId,
        public int $scheduleId,
        public string $exceptionDate,
        public ?int $substituteTeacherId,
        public ?int $substituteRoomId,
        public ?string $reason,
        public string $at,
        public ?string $correlationId,
        public ?int $createdBy,
    ) {}
}
