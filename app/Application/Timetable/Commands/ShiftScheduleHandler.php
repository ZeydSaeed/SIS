<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\ShiftScheduleResult;
use App\Domain\Timetable\Data\TimetableBoard;
use App\Domain\Timetable\Events\ScheduleUpdated;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\Services\ScheduleShiftPlanner;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;

/** «زحف المادة»: slides the lesson and the block it runs into by one period inside the section's day. */
final class ShiftScheduleHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ShiftSchedule';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly PeriodRepositoryInterface $periods,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly ScheduleShiftPlanner $planner,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ShiftScheduleResult
    {
        assert($command instanceof ShiftScheduleCommand);
        $key = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ShiftScheduleResult::fromIdempotency(array_map(intval(...), (array) $cached['moved']));
        }

        $lesson = $this->schedules->findById($command->schoolId, $command->scheduleId);
        if ($lesson === null) {
            return ShiftScheduleResult::failure('timetable.schedule_not_found');
        }
        if ($lesson->lifecycleStatus !== ScheduleLifecycleStatus::Active->value) {
            return ShiftScheduleResult::failure('timetable.schedule_not_active');
        }

        $dayLessons = [];
        foreach ($this->workspace->activeSchedules($command->schoolId, $lesson->academicYearId) as $s) {
            if ($s['section_id'] === $lesson->sectionId && $s['day_of_week'] === $lesson->dayOfWeek) {
                $dayLessons[$s['period_id']] = $s['id'];
            }
        }
        $day = new TimetableBoard($this->periods->listForSchool($command->schoolId), [], [], [], [], []);
        $plan = $this->planner->plan($day->lessonPeriodIds, $dayLessons, $lesson->id, $command->direction);
        if ($plan['error'] !== null) {
            return ShiftScheduleResult::failure($plan['error']);
        }

        $moved = array_column($plan['moves'], 'schedule_id');
        $this->unitOfWork->transaction(function () use ($command, $lesson, $plan, $key, $moved): void {
            $this->schedules->relocate($command->schoolId, array_map(
                static fn (array $m): array => ['schedule_id' => $m['schedule_id'], 'day' => $lesson->dayOfWeek, 'period_id' => $m['period_id']],
                $plan['moves'],
            ), now()->toIso8601String());
            foreach ($plan['moves'] as $move) {
                $this->outbox->stage(new ScheduleUpdated(
                    scheduleId: $move['schedule_id'],
                    schoolId: $command->schoolId,
                    sectionId: $lesson->sectionId,
                    academicYearId: $lesson->academicYearId,
                    dayOfWeek: $lesson->dayOfWeek,
                    periodId: $move['period_id'],
                    occurredAt: new \DateTimeImmutable,
                ));
            }
            $this->idempotency->store($key, self::COMMAND_NAME, ['moved' => $moved]);
        });

        return ShiftScheduleResult::success($moved);
    }
}
