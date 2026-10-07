<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Timetable\Results\ApplyTimetableGenerationResult;
use App\Application\Timetable\Support\EngineTransaction;
use App\Application\Timetable\Support\GenerationRunner;
use App\Domain\Timetable\Repositories\GenerationRunRepositoryInterface;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\ValueObjects\GenerationRunStatus;

final class ApplyTimetableGenerationHandler implements CommandHandler
{
    public function __construct(
        private readonly EngineTransaction $tx,
        private readonly GenerationRunRepositoryInterface $runs,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
    ) {}

    public function handle(Command $command): ApplyTimetableGenerationResult
    {
        assert($command instanceof ApplyTimetableGenerationCommand);

        return $this->tx->run(ApplyTimetableGenerationResult::class, $command->idempotencyKey, 'ApplyTimetableGeneration', function () use ($command): ApplyTimetableGenerationResult {
            $run = $this->runs->find($command->schoolId, $command->runId, true);
            if ($run === null || $run['status'] !== GenerationRunStatus::Succeeded->value) {
                return ApplyTimetableGenerationResult::failure('timetable.generation_not_appliable');
            }
            if ($run['is_what_if']) {
                return ApplyTimetableGenerationResult::failure('timetable.generation_what_if');
            }
            $result = $run['result'] ?? [];
            $live = $this->workspace->activeSchedules($command->schoolId, $run['academic_year_id']);
            if (($result['grid_fingerprint'] ?? '') !== GenerationRunner::gridFingerprint($live)) {
                return ApplyTimetableGenerationResult::failure('timetable.generation_stale');
            }
            $at = (new \DateTimeImmutable)->format('Y-m-d H:i:sP');
            $written = $this->schedules->replaceGrid($command->schoolId, $run['academic_year_id'], $result['replaced_ids'] ?? [], $result['rows'] ?? [], $at, $command->userId, $command->correlationId);
            $this->runs->transition($command->schoolId, $command->runId, GenerationRunStatus::Succeeded->value, GenerationRunStatus::Applied->value, $command->userId);
            $this->tx->stage('generation_applied', $command->schoolId, $run['academic_year_id'], $command->runId, $command->userId, ['rows' => $written, 'replaced' => count($result['replaced_ids'] ?? [])]);

            return ApplyTimetableGenerationResult::success($command->runId, ['rows' => $written]);
        });
    }
}
