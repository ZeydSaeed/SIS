<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\ReopenEnrollmentResult;
use App\Domain\Enrollment\Events\EnrollmentReopened;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;

final class ReopenEnrollmentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReopenEnrollment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReopenEnrollmentResult
    {
        assert($command instanceof ReopenEnrollmentCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return ReopenEnrollmentResult::fromIdempotency(
                    (int) $cached['enrollment_id'],
                    (int) $cached['status'],
                );
            }
        }

        $enrollment = $this->enrollments->findByIdAndSchool($command->enrollmentId, $command->schoolId);
        if ($enrollment === null) {
            return ReopenEnrollmentResult::failure(['enrollment.not_found']);
        }
        if ($enrollment->isActive()) {
            return ReopenEnrollmentResult::failure(['enrollment.already_open']);
        }
        if ($enrollment->status !== EnrollmentStatus::CANCELLED) {
            return ReopenEnrollmentResult::failure(['enrollment.not_reopenable']);
        }
        if ($this->enrollments->hasActiveEnrollment($enrollment->studentId, $enrollment->academicYearId)) {
            return ReopenEnrollmentResult::failure(['enrollment.student_has_active_enrollment']);
        }

        $ok = $this->unitOfWork->transaction(function () use ($command, $enrollment): bool {
            if (! $this->enrollments->reopen($command->enrollmentId)) {
                return false;
            }

            $this->outbox->stage(new EnrollmentReopened(
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment->studentId,
                schoolId: $enrollment->schoolId,
                academicYearId: $enrollment->academicYearId,
                occurredAt: new \DateTimeImmutable,
            ));

            return true;
        });

        if (! $ok) {
            return ReopenEnrollmentResult::failure(['enrollment.reopen_failed']);
        }

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_id' => $command->enrollmentId,
                'status' => EnrollmentStatus::ACTIVE,
            ]);
        }

        return ReopenEnrollmentResult::success($command->enrollmentId, EnrollmentStatus::ACTIVE);
    }
}
