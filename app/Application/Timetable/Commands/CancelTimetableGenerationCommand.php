<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** Stops a queued run at once, a running one at its next progress check (it keeps its best result). */
final readonly class CancelTimetableGenerationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $runId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
