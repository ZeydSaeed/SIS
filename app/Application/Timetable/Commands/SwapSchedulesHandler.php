<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\SwapSchedulesResult;
use App\Domain\Timetable\Data\ScheduleSnapshot;
use App\Domain\Timetable\Events\ScheduleUpdated;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;

/** Two lessons of one section trade day and period; each teacher must be free in the other's slot. */
final class SwapSchedulesHandler implements CommandHandler
{
    private const COMMAND_NAME = 'SwapSchedules';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SwapSchedulesResult
    {
        assert($command instanceof SwapSchedulesCommand);
        $key = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return SwapSchedulesResult::fromIdempotency(array_map(intval(...), (array) $cached['moved']));
        }

        if ($command->firstScheduleId === $command->secondScheduleId) {
            return SwapSchedulesResult::failure('timetable.swap_same_lesson');
        }
        $first = $this->schedules->findById($command->schoolId, $command->firstScheduleId);
        $second = $this->schedules->findById($command->schoolId, $command->secondScheduleId);
        if ($first === null || $second === null) {
            return SwapSchedulesResult::failure('timetable.schedule_not_found');
        }
        if ($first->lifecycleStatus !== ScheduleLifecycleStatus::Active->value || $second->lifecycleStatus !== ScheduleLifecycleStatus::Active->value) {
            return SwapSchedulesResult::failure('timetable.schedule_not_active');
        }
        if ($first->sectionId !== $second->sectionId || $first->academicYearId !== $second->academicYearId) {
            return SwapSchedulesResult::failure('timetable.swap_other_section');
        }

        $moved = [$first->id, $second->id];
        $this->unitOfWork->transaction(function () use ($command, $first, $second, $key, $moved): void {
            $this->schedules->relocate($command->schoolId, [
                ['schedule_id' => $first->id, 'day' => $second->dayOfWeek, 'period_id' => $second->periodId],
                ['schedule_id' => $second->id, 'day' => $first->dayOfWeek, 'period_id' => $first->periodId],
            ], now()->toIso8601String());
            $this->stageMoved($first, $second->dayOfWeek, $second->periodId);
            $this->stageMoved($second, $first->dayOfWeek, $first->periodId);
            $this->idempotency->store($key, self::COMMAND_NAME, ['moved' => $moved]);
        });

        return SwapSchedulesResult::success($moved);
    }

    private function stageMoved(ScheduleSnapshot $lesson, int $day, int $periodId): void
    {
        $this->outbox->stage(new ScheduleUpdated(
            scheduleId: $lesson->id,
            schoolId: $lesson->schoolId,
            sectionId: $lesson->sectionId,
            academicYearId: $lesson->academicYearId,
            dayOfWeek: $day,
            periodId: $periodId,
            occurredAt: new \DateTimeImmutable,
        ));
    }
}
