<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\UpdateScheduleExceptionResult;
use App\Domain\Timetable\Data\PersistScheduleExceptionData;
use App\Domain\Timetable\Events\ScheduleExceptionUpdated;
use App\Domain\Timetable\Repositories\ScheduleExceptionRepositoryInterface;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;

final class UpdateScheduleExceptionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateScheduleException';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ScheduleExceptionRepositoryInterface $exceptions,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateScheduleExceptionResult
    {
        assert($command instanceof UpdateScheduleExceptionCommand);

        $key = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateScheduleExceptionResult::fromIdempotency((int) $cached['exception_id']);
        }

        $at = now()->toIso8601String();
        $data = new PersistScheduleExceptionData(
            schoolId: $command->schoolId,
            scheduleId: $command->scheduleId,
            exceptionDate: $command->exceptionDate,
            substituteTeacherId: $command->substituteTeacherId,
            substituteRoomId: $command->substituteRoomId,
            reason: $command->reason,
            at: $at,
            correlationId: $command->correlationId,
            createdBy: $command->createdBy,
        );

        $this->unitOfWork->transaction(function () use ($command, $data, $key): void {
            $this->exceptions->update($command->exceptionId, $data);
            $this->outbox->stage(new ScheduleExceptionUpdated(
                $command->exceptionId,
                $command->schoolId,
                $command->scheduleId,
                $command->exceptionDate,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'exception_id' => $command->exceptionId,
            ]);
        });

        return UpdateScheduleExceptionResult::success($command->exceptionId);
    }
}
