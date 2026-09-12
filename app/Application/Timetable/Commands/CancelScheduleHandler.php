<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\CancelScheduleResult;
use App\Domain\Timetable\Events\ScheduleCancelled;
use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;

final class CancelScheduleHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CancelSchedule';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CancelScheduleResult
    {
        assert($command instanceof CancelScheduleCommand);

        $idempotencyKey = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return CancelScheduleResult::fromIdempotency((int) $cached['schedule_id']);
        }

        $current = $this->schedules->findById($command->schoolId, $command->scheduleId);
        if ($current === null) {
            throw ScheduleNotFoundException::forId($command->scheduleId);
        }

        $at = now()->toIso8601String();

        $this->unitOfWork->transaction(function () use ($command, $current, $at, $idempotencyKey): void {
            $this->schedules->cancel($command->schoolId, $command->scheduleId, $at);
            $this->outbox->stage(new ScheduleCancelled(
                scheduleId: $command->scheduleId,
                schoolId: $command->schoolId,
                academicYearId: $current->academicYearId,
                occurredAt: new \DateTimeImmutable,
            ));
            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'schedule_id' => $command->scheduleId,
            ]);
        });

        return CancelScheduleResult::success($command->scheduleId);
    }
}
