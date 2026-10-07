<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Contracts\TimetableGenerationDispatcher;
use App\Application\Timetable\Results\QueueTimetableGenerationResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Application\Timetable\Support\GenerationGate;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\Solver\TimetableSolverInterface;
use App\Domain\Timetable\ValueObjects\GenerationMode;

final class QueueTimetableGenerationHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly GenerationGate $gate,
        private readonly GenerationRunRepositoryInterface $runs,
        private readonly TimetableSolverInterface $solver,
        private readonly TimetableGenerationDispatcher $dispatcher,
    ) {}

    public function handle(Command $command): QueueTimetableGenerationResult
    {
        assert($command instanceof QueueTimetableGenerationCommand);
        $mode = GenerationMode::tryFrom($command->mode);
        if ($mode === null) {
            return QueueTimetableGenerationResult::failure('timetable.generation_mode_invalid');
        }
        $gate = $this->gate->check($command->schoolId, $command->academicYearId, $mode, $command->scope);
        if ($gate['errors'] !== []) {
            return new QueueTimetableGenerationResult(false, null, ['blockers' => $gate['blockers']], $gate['errors']);
        }

        $result = $this->tx->run(QueueTimetableGenerationResult::class, $command->idempotencyKey, 'QueueTimetableGeneration', function () use ($command, $mode): QueueTimetableGenerationResult {
            $id = $this->runs->createQueued($command->schoolId, $command->academicYearId, $mode->value, $command->isWhatIf(), $command->scope, $command->options, $this->solver->name(), $command->userId);
            if ($id === null) {
                return QueueTimetableGenerationResult::failure('timetable.generation_running');
            }
            $this->tx->stage('generation_queued', $command->schoolId, $command->academicYearId, $id, $command->userId, ['mode' => $mode->value, 'what_if' => $command->isWhatIf()]);

            return QueueTimetableGenerationResult::success($id);
        });
        // After commit: the job must see the run.
        if ($result->success && ! $result->fromIdempotencyCache && $result->id !== null) {
            $this->dispatcher->dispatch($command->schoolId, $result->id);
        }

        return $result;
    }
}
