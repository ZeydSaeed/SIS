<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\UpdateScheduleResult;
use App\Domain\Timetable\Data\PersistScheduleData;
use App\Domain\Timetable\Events\ScheduleUpdated;
use App\Domain\Timetable\Exceptions\ScheduleNotActiveException;
use App\Domain\Timetable\Exceptions\ScheduleNotFoundException;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;

final class UpdateScheduleHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateSchedule';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateScheduleResult
    {
        assert($command instanceof UpdateScheduleCommand);

        $idempotencyKey = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return UpdateScheduleResult::fromIdempotency((int) $cached['schedule_id']);
        }

        $current = $this->schedules->findById($command->schoolId, $command->scheduleId);
        if ($current === null) {
            throw ScheduleNotFoundException::forId($command->scheduleId);
        }
        if ($current->lifecycleStatus !== ScheduleLifecycleStatus::Active->value) {
            throw ScheduleNotActiveException::forId($command->scheduleId);
        }

        $at = now()->toIso8601String();
        $data = new PersistScheduleData(
            schoolId: $command->schoolId,
            sectionId: $command->sectionId,
            academicYearId: $command->academicYearId,
            dayOfWeek: $command->dayOfWeek,
            periodId: $command->periodId,
            subjectId: $command->subjectId,
            teacherId: $command->teacherId,
            roomId: $command->roomId,
            at: $at,
            correlationId: $command->correlationId,
            createdBy: $command->createdBy,
        );

        $this->unitOfWork->transaction(function () use ($command, $data, $idempotencyKey): void {
            $this->schedules->updateActive($command->scheduleId, $data);
            $this->outbox->stage(new ScheduleUpdated(
                scheduleId: $command->scheduleId,
                schoolId: $command->schoolId,
                sectionId: $command->sectionId,
                academicYearId: $command->academicYearId,
                dayOfWeek: $command->dayOfWeek,
                periodId: $command->periodId,
                occurredAt: new \DateTimeImmutable,
            ));
            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'schedule_id' => $command->scheduleId,
            ]);
        });

        return UpdateScheduleResult::success($command->scheduleId);
    }
}
