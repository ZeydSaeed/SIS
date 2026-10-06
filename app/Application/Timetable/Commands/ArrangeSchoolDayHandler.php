<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\ArrangeSchoolDayResult;
use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\PersistPeriodData;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use App\Domain\Timetable\Services\SchoolDayPlanner;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;
use App\Domain\Timetable\ValueObjects\PeriodType;

/**
 * Lays the day out again with the same lessons: every lesson period keeps its id (the timetable stays
 * on it), existing breaks are re-timed in order and missing ones are added. Periods are never deleted,
 * so a day with more breaks than the plan needs is refused.
 */
final class ArrangeSchoolDayHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ArrangeSchoolDay';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PeriodRepositoryInterface $periods,
        private readonly SchoolDayPlanner $planner,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ArrangeSchoolDayResult
    {
        assert($command instanceof ArrangeSchoolDayCommand);
        $key = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ArrangeSchoolDayResult::fromIdempotency((int) $cached['periods']);
        }

        $current = $this->periods->listForSchool($command->schoolId);
        usort($current, static fn (PeriodSnapshot $a, PeriodSnapshot $b): int => $a->periodNumber <=> $b->periodNumber);
        $lessons = array_values(array_filter($current, static fn (PeriodSnapshot $p): bool => $p->periodType === PeriodType::Lesson->value));
        $breaks = array_values(array_filter($current, static fn (PeriodSnapshot $p): bool => $p->periodType !== PeriodType::Lesson->value));
        if ($lessons === []) {
            return ArrangeSchoolDayResult::failure('timetable.arrange_no_lessons');
        }

        $plan = $this->planner->plan(count($lessons), $command->startTime, $command->lessonMinutes);
        if ($plan === null) {
            return ArrangeSchoolDayResult::failure('timetable.period_time_invalid');
        }
        if (count($breaks) > count($plan) - count($lessons)) {
            return ArrangeSchoolDayResult::failure('timetable.arrange_extra_breaks');
        }

        $day = [];
        foreach ($plan as $slot) {
            $existing = $slot['type'] === PeriodType::Lesson->value ? array_shift($lessons) : array_shift($breaks);
            $day[] = [
                'id' => $existing?->id,
                'data' => new PersistPeriodData($command->schoolId, $slot['number'], $slot['start'], $slot['end'], $slot['type']),
            ];
        }

        $this->unitOfWork->transaction(function () use ($command, $day, $key): void {
            $this->periods->replaceDay($command->schoolId, $day);
            $this->idempotency->store($key, self::COMMAND_NAME, ['periods' => count($day)]);
        });

        return ArrangeSchoolDayResult::success(count($day));
    }
}
