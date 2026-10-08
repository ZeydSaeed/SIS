<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** Creates a room (roomId null) or edits one (name, capacity, type — code and branch are fixed after creation). */
final readonly class SaveTimetableRoomCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public ?int $roomId,
        public int $branchId,
        public string $code,
        public string $name,
        public ?int $capacity,
        public int $roomType,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
