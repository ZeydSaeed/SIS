<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Attendance\Results\MarkSectionAttendanceResult;
use App\Domain\Attendance\Data\AttendanceSessionSnapshot;
use App\Domain\Attendance\Data\UpsertAttendanceRecordData;
use App\Domain\Attendance\Events\SectionAttendanceMarked;
use App\Domain\Attendance\Exceptions\CrossSchoolAttendanceAccessException;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Domain\Attendance\Exceptions\SessionNotOpenException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\Services\AttendanceEnrollmentGuard;
use App\Domain\Attendance\Services\AttendanceMarkPayloadGuard;
use App\Domain\Attendance\ValueObjects\SessionStatus;

final class MarkSectionAttendanceHandler implements CommandHandler
{
    public const COMMAND_NAME = 'MarkSectionAttendance';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceWriteRepositoryInterface $attendance,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): MarkSectionAttendanceResult
    {
        assert($command instanceof MarkSectionAttendanceCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return MarkSectionAttendanceResult::fromIdempotency(
                (int) $cached['session_id'],
                (int) $cached['marked_count'],
            );
        }

        AttendanceMarkPayloadGuard::assertValid($command->records);
        $resolvedSchoolId = $this->resolveAuthorizedSchool($command);

        $markedCount = $this->unitOfWork->transaction(
            fn (): int => $this->executeMark($command, $resolvedSchoolId),
        );

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
            'session_id' => $command->sessionId,
            'school_id' => $command->schoolId,
            'marked_count' => $markedCount,
        ]);

        return MarkSectionAttendanceResult::success($command->sessionId, $markedCount);
    }

    private function resolveAuthorizedSchool(MarkSectionAttendanceCommand $command): int
    {
        $session = $this->attendance->findSessionById($command->sessionId);
        if ($session === null) {
            throw SessionNotFoundException::forId($command->sessionId);
        }

        $resolvedSchoolId = $session->resolvedSchoolId
            ?? $this->attendance->resolveSchoolIdForSection($session->sectionId);

        if ($resolvedSchoolId === null
            || $resolvedSchoolId !== $command->schoolId
            || $session->academicYearId !== $command->academicYearId) {
            throw CrossSchoolAttendanceAccessException::create();
        }

        return $resolvedSchoolId;
    }

    private function executeMark(MarkSectionAttendanceCommand $command, int $resolvedSchoolId): int
    {
        $locked = $this->attendance->lockSessionStatus($command->sessionId);
        if ($locked === null) {
            throw SessionNotFoundException::forId($command->sessionId);
        }

        if ($locked->status !== SessionStatus::Open->value) {
            throw SessionNotOpenException::forId($command->sessionId, $locked->status);
        }

        $built = $this->buildRows($command, $locked, $resolvedSchoolId);
        $count = $this->attendance->upsertAttendanceRecords($built['rows']);

        $this->attendance->refreshDailySectionSummary(
            $locked->sectionId,
            $resolvedSchoolId,
            $command->academicYearId,
            $locked->sessionDate,
        );

        $this->outbox->stage(new SectionAttendanceMarked(
            sessionId: $command->sessionId,
            schoolId: $resolvedSchoolId,
            academicYearId: $command->academicYearId,
            sectionId: $locked->sectionId,
            count: $count,
            studentIds: $built['student_ids'],
            recordedBy: $command->recordedBy,
            occurredAt: new \DateTimeImmutable,
        ));

        return $count;
    }

    /**
     * @return array{rows: list<UpsertAttendanceRecordData>, student_ids: list<int>}
     */
    private function buildRows(
        MarkSectionAttendanceCommand $command,
        AttendanceSessionSnapshot $locked,
        int $resolvedSchoolId,
    ): array {
        $rows = [];
        $studentIds = [];

        foreach ($command->records as $row) {
            $enrollmentId = (int) $row['enrollmentId'];
            $studentId = (int) $row['studentId'];
            $notes = array_key_exists('notes', $row) ? $row['notes'] : null;

            AttendanceEnrollmentGuard::assertValidForMark(
                $this->attendance->loadEnrollmentForMark($enrollmentId),
                $enrollmentId,
                $studentId,
                $resolvedSchoolId,
                $locked->academicYearId,
                $locked->sectionId,
                $locked->sessionDate,
            );

            $rows[] = new UpsertAttendanceRecordData(
                sessionId: $command->sessionId,
                studentId: $studentId,
                enrollmentId: $enrollmentId,
                academicYearId: $command->academicYearId,
                schoolId: $resolvedSchoolId,
                attendanceDate: $locked->sessionDate,
                status: (int) $row['status'],
                notes: is_string($notes) || $notes === null ? $notes : null,
                recordedBy: $command->recordedBy,
            );
            $studentIds[] = $studentId;
        }

        return ['rows' => $rows, 'student_ids' => $studentIds];
    }
}
