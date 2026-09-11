<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Attendance\Results\CorrectAttendanceRecordResult;
use App\Domain\Attendance\Events\AttendanceCorrected;
use App\Domain\Attendance\Exceptions\AttendanceRecordNotFoundException;
use App\Domain\Attendance\Exceptions\CrossSchoolAttendanceAccessException;
use App\Domain\Attendance\Exceptions\InvalidAttendanceStatusException;
use App\Domain\Attendance\Exceptions\InvalidCorrectionReasonException;
use App\Domain\Attendance\Exceptions\SessionCancelledException;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\AttendanceRecordStatus;
use App\Domain\Attendance\ValueObjects\SessionStatus;

final class CorrectAttendanceRecordHandler implements CommandHandler
{
    public const COMMAND_NAME = 'CorrectAttendanceRecord';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceWriteRepositoryInterface $attendance,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CorrectAttendanceRecordResult
    {
        assert($command instanceof CorrectAttendanceRecordCommand);

        if (trim($command->reason) === '') {
            throw InvalidCorrectionReasonException::empty();
        }

        if (! AttendanceRecordStatus::isValid($command->newStatus)) {
            throw InvalidAttendanceStatusException::forValue($command->newStatus);
        }

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return CorrectAttendanceRecordResult::fromIdempotency(
                (int) $cached['record_id'],
                (int) $cached['previous_status'],
                (int) $cached['new_status'],
            );
        }

        [$recordId, $previousStatus] = $this->unitOfWork->transaction(function () use ($command): array {
            $session = $this->attendance->lockSessionStatus($command->sessionId);
            if ($session === null) {
                throw SessionNotFoundException::forId($command->sessionId);
            }

            if ($session->status === SessionStatus::Cancelled->value) {
                throw SessionCancelledException::forId($command->sessionId);
            }

            $resolvedSchoolId = $session->resolvedSchoolId
                ?? $this->attendance->resolveSchoolIdForSection($session->sectionId);

            if ($resolvedSchoolId === null || $resolvedSchoolId !== $command->schoolId) {
                throw CrossSchoolAttendanceAccessException::create();
            }

            if ($session->academicYearId !== $command->academicYearId) {
                throw CrossSchoolAttendanceAccessException::create();
            }

            $record = $this->attendance->findRecord(
                $command->sessionId,
                $command->studentId,
                $command->academicYearId,
                $command->schoolId,
            );

            if ($record === null) {
                throw AttendanceRecordNotFoundException::forStudent($command->sessionId, $command->studentId);
            }

            $previousStatus = $record->status;
            $previousNotes = $record->notes;

            $this->attendance->updateRecordStatus(
                $record->id,
                $command->academicYearId,
                $command->schoolId,
                $command->newStatus,
                $command->newNotes,
                $command->recordedBy,
            );

            $this->attendance->refreshDailySectionSummary(
                $session->sectionId,
                $command->schoolId,
                $command->academicYearId,
                $session->sessionDate,
            );

            $this->outbox->stage(new AttendanceCorrected(
                recordId: $record->id,
                sessionId: $command->sessionId,
                studentId: $command->studentId,
                enrollmentId: $record->enrollmentId,
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                previousStatus: $previousStatus,
                newStatus: $command->newStatus,
                previousNotes: $previousNotes,
                newNotes: $command->newNotes,
                reason: $command->reason,
                recordedBy: $command->recordedBy,
                idempotencyKey: $command->idempotencyKey,
                occurredAt: new \DateTimeImmutable,
            ));

            return [$record->id, $previousStatus];
        });

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
            'record_id' => $recordId,
            'previous_status' => $previousStatus,
            'new_status' => $command->newStatus,
            'school_id' => $command->schoolId,
            'session_id' => $command->sessionId,
        ]);

        return CorrectAttendanceRecordResult::success($recordId, $previousStatus, $command->newStatus);
    }
}
