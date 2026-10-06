<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\CreatePeriodResult;
use App\Domain\Timetable\Data\PersistPeriodData;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use App\Domain\Timetable\Services\PeriodTimeGuard;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;

/** Adds a period to the school day («توقيت الحصص»). */
final class CreatePeriodHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreatePeriod';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly PeriodRepositoryInterface $periods,
        private readonly PeriodTimeGuard $guard,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreatePeriodResult
    {
        assert($command instanceof CreatePeriodCommand);
        $key = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreatePeriodResult::fromIdempotency((int) $cached['period_id']);
        }

        $data = new PersistPeriodData(
            schoolId: $command->schoolId,
            periodNumber: $command->periodNumber,
            startTime: $command->startTime,
            endTime: $command->endTime,
            periodType: $command->periodType,
        );

        $error = $this->guard->error($data, $this->periods->listForSchool($command->schoolId));
        if ($error !== null) {
            return CreatePeriodResult::failure($error);
        }

        $periodId = $this->unitOfWork->transaction(function () use ($data, $key): int {
            $id = $this->periods->insert($data);
            $this->idempotency->store($key, self::COMMAND_NAME, ['period_id' => $id]);

            return $id;
        });

        return CreatePeriodResult::success($periodId);
    }
}
