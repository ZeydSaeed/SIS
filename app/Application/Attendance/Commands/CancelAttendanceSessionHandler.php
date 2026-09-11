<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Results\CancelAttendanceSessionResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Data\AttendanceSessionSnapshot;
use App\Domain\Attendance\Events\AttendanceSessionCancelled;
use App\Domain\Attendance\Exceptions\CrossSchoolAttendanceAccessException;
use App\Domain\Attendance\Exceptions\IdempotencyPayloadConflictException;
use App\Domain\Attendance\Exceptions\InvalidCancellationReasonException;
use App\Domain\Attendance\Exceptions\SessionCancelConflictException;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Domain\Shared\Exceptions\SisDomainException;

final class CancelAttendanceSessionHandler implements CommandHandler
{
    public const COMMAND_NAME = 'CancelAttendanceSession';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceWriteRepositoryInterface $attendance,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CancelAttendanceSessionResult
    {
        assert($command instanceof CancelAttendanceSessionCommand);

        $reason = $this->requireReason($command->reason);
        $idempotencyKey = $this->requireIdempotencyKey($command->idempotencyKey);

        $cached = $this->idempotency->find($idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            $this->assertCachedPayloadMatches($cached, $command, $reason);

            return CancelAttendanceSessionResult::fromIdempotency(
                (int) $cached['session_id'],
                (int) $cached['previous_status'],
                (int) $cached['new_status'],
            );
        }

        $session = $this->requireOwnedSession($command);
        $previousStatus = $this->cancelInTransaction($command, $session, $reason);

        $this->idempotency->store($idempotencyKey, self::COMMAND_NAME, [
            'session_id' => $command->sessionId,
            'school_id' => $command->schoolId,
            'reason' => $reason,
            'previous_status' => $previousStatus,
            'new_status' => SessionStatus::Cancelled->value,
        ]);

        return CancelAttendanceSessionResult::success(
            $command->sessionId,
            $previousStatus,
            SessionStatus::Cancelled->value,
        );
    }

    private function requireReason(string $reason): string
    {
        $trimmed = trim($reason);
        if ($trimmed === '') {
            throw InvalidCancellationReasonException::empty();
        }

        return $trimmed;
    }

    private function requireIdempotencyKey(string $idempotencyKey): string
    {
        $trimmed = trim($idempotencyKey);
        if ($trimmed === '') {
            throw SisDomainException::withCode(
                'attendance.idempotency_key_required',
                'Idempotency key is required for CancelAttendanceSession.',
            );
        }

        return $trimmed;
    }

    private function requireOwnedSession(CancelAttendanceSessionCommand $command): AttendanceSessionSnapshot
    {
        $session = $this->attendance->findSessionById($command->sessionId);
        if ($session === null) {
            throw SessionNotFoundException::forId($command->sessionId);
        }

        $resolvedSchoolId = $session->resolvedSchoolId
            ?? $this->attendance->resolveSchoolIdForSection($session->sectionId)
            ?? 0;

        if ($resolvedSchoolId !== $command->schoolId) {
            throw CrossSchoolAttendanceAccessException::create();
        }

        return $session;
    }

    private function cancelInTransaction(
        CancelAttendanceSessionCommand $command,
        AttendanceSessionSnapshot $session,
        string $reason,
    ): int {
        return $this->unitOfWork->transaction(function () use ($command, $session, $reason): int {
            $previous = $this->attendance->cancelSessionIfOpenOrClosed($command->sessionId);
            if ($previous === null) {
                throw SessionCancelConflictException::forId($command->sessionId);
            }

            $schoolId = $session->resolvedSchoolId
                ?? $this->attendance->resolveSchoolIdForSection($session->sectionId)
                ?? $command->schoolId;

            $this->outbox->stage(new AttendanceSessionCancelled(
                sessionId: $command->sessionId,
                schoolId: $schoolId,
                academicYearId: $session->academicYearId,
                previousStatus: $previous,
                newStatus: SessionStatus::Cancelled->value,
                reason: $reason,
                cancelledBy: $command->cancelledBy,
                occurredAt: new \DateTimeImmutable,
            ));

            return $previous;
        });
    }

    /**
     * @param  array<string, mixed>  $cached
     */
    private function assertCachedPayloadMatches(
        array $cached,
        CancelAttendanceSessionCommand $command,
        string $reason,
    ): void {
        $matches = [
            (int) ($cached['session_id'] ?? 0) === $command->sessionId,
            (int) ($cached['school_id'] ?? 0) === $command->schoolId,
            (string) ($cached['reason'] ?? '') === $reason,
        ];

        if (in_array(false, $matches, true)) {
            throw IdempotencyPayloadConflictException::mismatch();
        }
    }
}
