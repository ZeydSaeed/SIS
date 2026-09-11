<?php

namespace App\Application\Attendance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Attendance\Results\CreateAttendanceSessionResult;
use App\Domain\Attendance\Events\AttendanceSessionCreated;
use App\Domain\Attendance\Exceptions\AcademicYearDateOutOfBoundsException;
use App\Domain\Attendance\Exceptions\CrossSchoolAttendanceAccessException;
use App\Domain\Attendance\Exceptions\DuplicateOpenAttendanceSessionException;
use App\Domain\Attendance\Exceptions\InvalidPeriodForSchoolException;
use App\Domain\Attendance\Exceptions\SchoolResolutionFailedException;
use App\Domain\Attendance\Repositories\AttendanceWriteRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SessionStatus;

final class CreateAttendanceSessionHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateAttendanceSession';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceWriteRepositoryInterface $attendance,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateAttendanceSessionResult
    {
        assert($command instanceof CreateAttendanceSessionCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return CreateAttendanceSessionResult::fromIdempotency((int) $cached['session_id']);
            }
        }

        $resolvedSchoolId = $this->attendance->resolveSchoolIdForSection($command->sectionId);
        if ($resolvedSchoolId === null) {
            throw SchoolResolutionFailedException::forSection($command->sectionId);
        }

        if ($resolvedSchoolId !== $command->schoolId) {
            throw CrossSchoolAttendanceAccessException::create();
        }

        if (! $this->attendance->academicYearContainsDate($command->academicYearId, $command->sessionDate)) {
            throw AcademicYearDateOutOfBoundsException::forDate($command->academicYearId, $command->sessionDate);
        }

        if ($command->periodId !== null
            && ! $this->attendance->periodBelongsToSchool($command->periodId, $command->schoolId)) {
            throw InvalidPeriodForSchoolException::forPeriod($command->periodId, $command->schoolId);
        }

        // Early soft guard — DB partial UNIQUE is the concurrency authority.
        if ($this->attendance->findDuplicateOpenSession(
            $command->schoolId,
            $command->academicYearId,
            $command->sectionId,
            $command->subjectId,
            $command->sessionDate,
            $command->periodId,
        ) !== null) {
            throw DuplicateOpenAttendanceSessionException::forNaturalKey(
                $command->schoolId,
                $command->academicYearId,
                $command->sectionId,
                $command->subjectId,
                $command->sessionDate,
                $command->periodId,
            );
        }

        $sessionId = $this->unitOfWork->transaction(function () use ($command): int {
            $id = $this->attendance->insertSession(
                sectionId: $command->sectionId,
                subjectId: $command->subjectId,
                academicYearId: $command->academicYearId,
                sessionDate: $command->sessionDate,
                periodId: $command->periodId,
                teacherId: $command->teacherId,
                status: SessionStatus::Open->value,
                schoolId: $command->schoolId,
            );

            $this->outbox->stage(new AttendanceSessionCreated(
                sessionId: $id,
                schoolId: $command->schoolId,
                academicYearId: $command->academicYearId,
                sectionId: $command->sectionId,
                subjectId: $command->subjectId,
                sessionDate: $command->sessionDate,
                teacherId: $command->teacherId,
                periodId: $command->periodId,
                createdBy: $command->createdBy,
                occurredAt: new \DateTimeImmutable,
            ));

            return $id;
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'session_id' => $sessionId,
                'school_id' => $command->schoolId,
            ]);
        }

        return CreateAttendanceSessionResult::success($sessionId);
    }
}
