<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** Runs a queued generation (the queue job's entry point). */
final readonly class ExecuteTimetableGenerationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $runId,
    ) {}
}
