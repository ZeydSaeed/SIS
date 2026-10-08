<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** Creates a workshop (workshopId null) or edits one (name, capacity, safety capacity, room — the code is fixed). */
final readonly class SaveTimetableWorkshopCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public ?int $workshopId,
        public string $code,
        public string $name,
        public int $capacity,
        public int $safetyCapacity,
        public ?int $roomId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
