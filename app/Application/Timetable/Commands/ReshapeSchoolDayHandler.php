<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\ReshapeSchoolDayResult;
use App\Domain\Timetable\Data\PersistPeriodData;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use App\Domain\Timetable\Services\BellScheduleCalculator;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;
use App\Domain\Timetable\ValueObjects\PeriodPresentation;

/**
 * Applies one school-day operation and writes the whole re-timed day in one transaction (numbers parked first,
 * so renumbering never trips the active-number UNIQUE). Lesson periods keep their ids — placed lessons stay put;
 * a removed break is retired, never deleted (attendance and history keep their references).
 */
final class ReshapeSchoolDayHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReshapeSchoolDay';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PeriodRepositoryInterface $periods,
        private readonly BellScheduleCalculator $calculator,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReshapeSchoolDayResult
    {
        assert($command instanceof ReshapeSchoolDayCommand);
        $key = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReshapeSchoolDayResult::fromIdempotency((int) $cached['periods']);
        }
        $presentationError = $command->presentation?->rejection();
        if ($presentationError !== null) {
            return ReshapeSchoolDayResult::failure($presentationError);
        }

        $outcome = $this->plan($command);
        if (is_string($outcome)) {
            return ReshapeSchoolDayResult::failure($outcome);
        }
        [$plan, $retired] = $outcome;

        $day = array_map(static fn (array $slot): array => [
            'id' => $slot['id'],
            'data' => new PersistPeriodData($command->schoolId, $slot['number'], $slot['start'], $slot['end'], $slot['type'], $slot['presentation']),
        ], $plan);

        $this->unitOfWork->transaction(function () use ($command, $day, $retired, $key): void {
            if ($retired !== null) {
                $this->periods->retire($command->schoolId, $retired);
            }
            $this->periods->replaceDay($command->schoolId, $day);
            $this->idempotency->store($key, self::COMMAND_NAME, ['periods' => count($day)]);
        });

        return ReshapeSchoolDayResult::success(count($day));
    }

    /** @return array{0: list<array<string, mixed>>, 1: int|null}|string */
    private function plan(ReshapeSchoolDayCommand $command): array|string
    {
        $day = $this->periods->listForSchool($command->schoolId);
        $id = (int) $command->periodId;
        $minutes = (int) $command->minutes;

        $plan = match ($command->operation) {
            'insert_break' => $this->calculator->insertBreak($day, $command->afterPeriodId, $minutes, $command->presentation ?? new PeriodPresentation),
            'move_break' => $this->calculator->moveBreak($day, $id, $command->afterPeriodId),
            'resize' => $this->calculator->resize($day, $id, $minutes),
            'retime' => $this->calculator->retime($day, $id, (string) $command->startTime, (string) $command->endTime, $command->cascade),
            'fixed_pattern' => $this->calculator->fixedPattern($day, (string) $command->startTime, $minutes),
            'remove_break' => $this->calculator->removeBreak($day, $id),
            default => 'timetable.day_operation_invalid',
        };
        if (is_string($plan)) {
            return $plan;
        }

        if ($command->operation === 'remove_break') {
            return [$plan['day'], $plan['retired']];
        }
        // An edit re-times and re-labels the period in one step.
        if ($command->presentation !== null && $command->operation !== 'insert_break') {
            $plan = array_map(static fn (array $slot): array => $slot['id'] === $command->periodId ? ['presentation' => $command->presentation] + $slot : $slot, $plan);
        }

        return [$plan, null];
    }
}
