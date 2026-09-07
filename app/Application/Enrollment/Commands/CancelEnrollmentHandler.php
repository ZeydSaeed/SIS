<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\CancelEnrollmentResult;
use App\Domain\Enrollment\Events\EnrollmentCancelled;
use App\Domain\Enrollment\Exceptions\EnrollmentNotActiveException;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Enrollment\Exceptions\InvalidEnrollmentPlacementException;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;

final class CancelEnrollmentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CancelEnrollment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CancelEnrollmentResult
    {
        assert($command instanceof CancelEnrollmentCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return CancelEnrollmentResult::fromIdempotency(
                    (int) $cached['enrollment_id'],
                    (string) $cached['effective_to'],
                    EnrollmentStatus::CANCELLED,
                );
            }
        }

        $enrollment = $this->enrollments->findById($command->enrollmentId);
        if ($enrollment === null || $enrollment->schoolId !== $command->schoolId) {
            throw EnrollmentNotFoundException::forId($command->enrollmentId);
        }

        if (! $enrollment->isActive()) {
            throw EnrollmentNotActiveException::forId($command->enrollmentId);
        }

        if ($command->effectiveTo < $enrollment->effectiveFrom) {
            throw InvalidEnrollmentPlacementException::forReason(
                'Effective end date cannot be before effective start date.',
            );
        }

        $this->unitOfWork->transaction(function () use ($command, $enrollment): void {
            $this->enrollments->cancel($command->enrollmentId, $command->effectiveTo);

            $this->outbox->stage(new EnrollmentCancelled(
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment->studentId,
                schoolId: $enrollment->schoolId,
                academicYearId: $enrollment->academicYearId,
                effectiveTo: $command->effectiveTo,
                cancelledBy: $command->cancelledBy,
                occurredAt: new \DateTimeImmutable,
            ));
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_id' => $command->enrollmentId,
                'effective_to' => $command->effectiveTo,
            ]);
        }

        return CancelEnrollmentResult::success(
            $command->enrollmentId,
            $command->effectiveTo,
            EnrollmentStatus::CANCELLED,
        );
    }
}
