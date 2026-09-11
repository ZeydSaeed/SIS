<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Attendance\Results\CloseAttendanceSessionResult;
use App\Domain\Attendance\Events\AttendanceSessionClosed;
use App\Domain\Attendance\Exceptions\CrossSchoolAttendanceAccessException;
use App\Domain\Attendance\Exceptions\SessionCloseConflictException;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;

final class CloseAttendanceSessionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CloseAttendanceSession';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceWriteRepositoryInterface $attendance,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CloseAttendanceSessionResult
    {
        assert($command instanceof CloseAttendanceSessionCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return CloseAttendanceSessionResult::fromIdempotency((int) $cached['session_id']);
            }
        }

        $session = $this->attendance->findSessionById($command->sessionId);
        if ($session === null) {
            throw SessionNotFoundException::forId($command->sessionId);
        }

        $resolvedSchoolId = $session->resolvedSchoolId
            ?? $this->attendance->resolveSchoolIdForSection($session->sectionId);

        if ($resolvedSchoolId === null || $resolvedSchoolId !== $command->schoolId) {
            throw CrossSchoolAttendanceAccessException::create();
        }

        $closed = $this->unitOfWork->transaction(function () use ($command, $session, $resolvedSchoolId): bool {
            $ok = $this->attendance->closeSessionIfOpen($command->sessionId);
            if (! $ok) {
                return false;
            }

            $this->outbox->stage(new AttendanceSessionClosed(
                sessionId: $command->sessionId,
                schoolId: $resolvedSchoolId,
                academicYearId: $session->academicYearId,
                closedBy: $command->closedBy,
                occurredAt: new \DateTimeImmutable,
            ));

            return true;
        });

        if (! $closed) {
            throw SessionCloseConflictException::forId($command->sessionId);
        }

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'session_id' => $command->sessionId,
                'school_id' => $command->schoolId,
            ]);
        }

        return CloseAttendanceSessionResult::success($command->sessionId);
    }
}
