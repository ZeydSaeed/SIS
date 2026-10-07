<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** Ends an activity (status 2 + effective_to); its lessons on the grid stay until regenerated. */
final readonly class EndTimetableActivityCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $activityId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
