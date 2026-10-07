<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\DiscardTimetableGenerationResult;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\ValueObjects\GenerationRunStatus;

final class DiscardTimetableGenerationHandler implements CommandHandler
{
    public function __construct(
        private readonly GenerationRunRepositoryInterface $runs,
    ) {}

    public function handle(Command $command): DiscardTimetableGenerationResult
    {
        assert($command instanceof DiscardTimetableGenerationCommand);
        $discarded = $this->runs->transition($command->schoolId, $command->runId, GenerationRunStatus::Succeeded->value, GenerationRunStatus::Discarded->value, $command->userId)
            || $this->runs->transition($command->schoolId, $command->runId, GenerationRunStatus::Cancelled->value, GenerationRunStatus::Discarded->value, $command->userId);

        return $discarded
            ? DiscardTimetableGenerationResult::success($command->runId)
            : DiscardTimetableGenerationResult::failure('timetable.generation_not_discardable');
    }
}
