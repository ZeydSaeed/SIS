<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Timetable\Results\CreateScheduleResult;
use App\Domain\Timetable\Data\PersistScheduleData;
use App\Domain\Timetable\Events\ScheduleCreated;
use App\Domain\Timetable\Repositories\ScheduleRepositoryInterface;
use App\Domain\Timetable\Support\ScheduleIdempotencyGuard;

final class CreateScheduleHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateSchedule';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ScheduleRepositoryInterface $schedules,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateScheduleResult
    {
        assert($command instanceof CreateScheduleCommand);

        $idempotencyKey = ScheduleIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateScheduleResult::fromIdempotency((int) $cached['schedule_id']);
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

        $scheduleId = $this->unitOfWork->transaction(function () use (
            $command,
            $data,
            $idempotencyKey,
        ): int {
            $id = $this->schedules->insertActive($data);
            $this->outbox->stage(new ScheduleCreated(
                scheduleId: $id,
                schoolId: $command->schoolId,
                sectionId: $command->sectionId,
                academicYearId: $command->academicYearId,
                dayOfWeek: $command->dayOfWeek,
                periodId: $command->periodId,
                occurredAt: new \DateTimeImmutable,
            ));
            $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
                'schedule_id' => $id,
            ]);

            return $id;
        });

        return CreateScheduleResult::success($scheduleId);
    }
}
