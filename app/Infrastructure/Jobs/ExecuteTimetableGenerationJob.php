<?php

namespace App\Infrastructure\Jobs;

use App\Application\Timetable\Commands\ExecuteTimetableGenerationCommand;
use App\Application\Timetable\Commands\ExecuteTimetableGenerationHandler;
use App\Security\Context\SchoolContextScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Runs one timetable generation off the HTTP request, inside the school's RLS context. */
final class ExecuteTimetableGenerationJob implements ShouldQueue
{
    use Queueable;

    /** One attempt: a run is not retried blindly (its row records the failure). */
    public int $tries = 1;

    /** The solver's own budget is at most 120 s; this is the hard stop. */
    public int $timeout = 300;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $runId,
    ) {}

    public function handle(SchoolContextScope $scope, ExecuteTimetableGenerationHandler $handler): void
    {
        $scope->run($this->schoolId, fn () => $handler->handle(new ExecuteTimetableGenerationCommand($this->schoolId, $this->runId)));
    }
}
