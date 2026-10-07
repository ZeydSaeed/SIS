<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «تجاهل»: a reviewed run is not applied (kept for the record). */
final readonly class DiscardTimetableGenerationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $runId,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
