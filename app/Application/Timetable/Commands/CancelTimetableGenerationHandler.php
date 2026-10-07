<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\CancelTimetableGenerationResult;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;

final class CancelTimetableGenerationHandler implements CommandHandler
{
    public function __construct(
        private readonly GenerationRunRepositoryInterface $runs,
    ) {}

    public function handle(Command $command): CancelTimetableGenerationResult
    {
        assert($command instanceof CancelTimetableGenerationCommand);

        return $this->runs->requestCancel($command->schoolId, $command->runId)
            ? CancelTimetableGenerationResult::success($command->runId)
            : CancelTimetableGenerationResult::failure('timetable.generation_not_active');
    }
}
