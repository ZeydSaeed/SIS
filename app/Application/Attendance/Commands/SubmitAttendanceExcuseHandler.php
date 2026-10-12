<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\ExcuseDocumentStorageInterface;
use App\Application\Attendance\Results\SubmitAttendanceExcuseResult;
use App\Application\Attendance\Support\ExcuseSubmissionValidator;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use App\Domain\Attendance\Repositories\AttendanceExcuseRepositoryInterface;

/** Records an excuse as pending — it changes no attendance record until an authorised reviewer approves it. */
final class SubmitAttendanceExcuseHandler implements CommandHandler
{
    public const COMMAND_NAME = 'SubmitAttendanceExcuse';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceExcuseRepositoryInterface $excuses,
        private readonly ExcuseSubmissionValidator $validator,
        private readonly ExcuseDocumentStorageInterface $documents,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SubmitAttendanceExcuseResult
    {
        assert($command instanceof SubmitAttendanceExcuseCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return SubmitAttendanceExcuseResult::fromIdempotency($cached);
        }

        $enrollment = $this->validator->validate($command);

        $id = $this->unitOfWork->transaction(function () use ($command, $enrollment): int {
            $documentId = $command->document === null ? $command->documentFileId : $this->documents->store(
                $command->schoolId, $enrollment->studentId, $command->excuseType, (string) $command->document['name'], (string) $command->document['mime'],
                (string) $command->document['contents'], $command->submittedBy, $command->idempotencyKey,
            );
            $id = $this->excuses->create(
                $command->schoolId, $command->academicYearId, $enrollment->studentId, $enrollment->id, $command->dateFrom, $command->dateTo,
                $command->sessionId, $command->excuseType, trim($command->reason), $documentId, $command->submittedBy,
            );
            $this->outbox->stage(new AttendanceWorkflowEvent(
                AttendanceWorkflowEvent::EXCUSE_SUBMITTED, $command->schoolId, $command->academicYearId, 'attendance_excuse', $id, $command->submittedBy,
                [], ['student_id' => $enrollment->studentId, 'date_from' => $command->dateFrom, 'date_to' => $command->dateTo, 'excuse_type' => $command->excuseType],
                new \DateTimeImmutable,
            ));

            return $id;
        });

        $data = ['excuse_id' => $id];
        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return SubmitAttendanceExcuseResult::success($data);
    }
}
