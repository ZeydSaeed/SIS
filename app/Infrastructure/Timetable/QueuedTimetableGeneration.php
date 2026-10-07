<?php

namespace App\Infrastructure\Timetable;

use App\Application\Timetable\Contracts\TimetableGenerationDispatcher;
use App\Infrastructure\Jobs\ExecuteTimetableGenerationJob;

final class QueuedTimetableGeneration implements TimetableGenerationDispatcher
{
    public function dispatch(int $schoolId, int $runId): void
    {
        ExecuteTimetableGenerationJob::dispatch($schoolId, $runId);
    }
}
