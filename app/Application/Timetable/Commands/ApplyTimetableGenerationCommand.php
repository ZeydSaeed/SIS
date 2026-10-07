<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «اعتماد النتيجة»: writes a succeeded run onto the working grid — the lessons it regenerated are cancelled
 * (history), its rows inserted, locked lessons untouched. Refused for what-if runs and when the grid changed
 * since the run (re-generate).
 */
final readonly class ApplyTimetableGenerationCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $runId,
        public ?int $userId,
        public ?string $correlationId,
        public ?string $idempotencyKey = null,
    ) {}
}
