<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\UpdatePeriodResult;
use App\Domain\Timetable\Data\PersistPeriodData;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use App\Domain\Timetable\Services\PeriodTimeGuard;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;
use App\Domain\Timetable\ValueObjects\PeriodType;

/** Re-times a period. A period holding active lessons cannot become a break. */
final class UpdatePeriodHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdatePeriod';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PeriodRepositoryInterface $periods,
        private readonly PeriodTimeGuard $guard,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdatePeriodResult
    {
        assert($command instanceof UpdatePeriodCommand);
        $key = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdatePeriodResult::fromIdempotency((int) $cached['period_id']);
        }

        if ($this->periods->find($command->schoolId, $command->periodId) === null) {
            return UpdatePeriodResult::failure('timetable.period_not_in_school');
        }

        $data = new PersistPeriodData(
            schoolId: $command->schoolId,
            periodNumber: $command->periodNumber,
            startTime: $command->startTime,
            endTime: $command->endTime,
            periodType: $command->periodType,
            presentation: $command->presentation,
        );
        $presentationError = $command->presentation?->rejection();
        if ($presentationError !== null) {
            return UpdatePeriodResult::failure($presentationError);
        }

        $error = $this->guard->error($data, $this->periods->listForSchool($command->schoolId), $command->periodId);
        if ($error !== null) {
            return UpdatePeriodResult::failure($error);
        }

        if ($command->periodType !== PeriodType::Lesson->value && $this->periods->hasActiveSchedules($command->schoolId, $command->periodId)) {
            return UpdatePeriodResult::failure('timetable.period_has_lessons');
        }

        $this->unitOfWork->transaction(function () use ($command, $data, $key): void {
            $this->periods->update($command->periodId, $data);
            $this->idempotency->store($key, self::COMMAND_NAME, ['period_id' => $command->periodId]);
        });

        return UpdatePeriodResult::success($command->periodId);
    }
}
