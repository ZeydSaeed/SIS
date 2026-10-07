<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\ExecuteTimetableGenerationResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Application\Timetable\Support\GenerationRunner;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\ValueObjects\GenerationRunStatus;

/**
 * Runs outside a transaction on purpose: progress and the cancel flag are read and written while the solver
 * works; the result is stored in one write at the end.
 */
final class ExecuteTimetableGenerationHandler implements CommandHandler
{
    public function __construct(
        private readonly GenerationRunner $runner,
        private readonly GenerationRunRepositoryInterface $runs,
        private readonly EngineTransaction $tx,
    ) {}

    public function handle(Command $command): ExecuteTimetableGenerationResult
    {
        assert($command instanceof ExecuteTimetableGenerationCommand);
        if (! $this->runner->execute($command->schoolId, $command->runId)) {
            return ExecuteTimetableGenerationResult::failure('timetable.generation_not_queued');
        }
        $run = $this->runs->find($command->schoolId, $command->runId);
        $ok = $run !== null && $run['status'] === GenerationRunStatus::Succeeded->value;

        return $this->tx->run(ExecuteTimetableGenerationResult::class, null, 'ExecuteTimetableGeneration', function () use ($command, $run, $ok): ExecuteTimetableGenerationResult {
            $this->tx->stage($ok ? 'generation_completed' : 'generation_failed', $command->schoolId, (int) ($run['academic_year_id'] ?? 0), $command->runId, $run['requested_by'] ?? null, [
                'placed' => $run['placed'] ?? null, 'unplaced' => $run['unplaced'] ?? null, 'hard_violations' => $run['hard_violations'] ?? null,
            ]);

            return ExecuteTimetableGenerationResult::success($command->runId, ['status' => $run['status'] ?? null]);
        });
    }
}
