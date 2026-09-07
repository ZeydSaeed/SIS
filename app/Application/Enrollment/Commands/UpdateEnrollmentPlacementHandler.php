<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\UpdateEnrollmentPlacementResult;
use App\Domain\Enrollment\Events\EnrollmentPlacementUpdated;
use App\Domain\Enrollment\Exceptions\EnrollmentNotActiveException;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Enrollment\Exceptions\InvalidEnrollmentPlacementException;
use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;

final class UpdateEnrollmentPlacementHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UpdateEnrollmentPlacement';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly EnrollmentPlacementRepositoryInterface $placement,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UpdateEnrollmentPlacementResult
    {
        assert($command instanceof UpdateEnrollmentPlacementCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return UpdateEnrollmentPlacementResult::fromIdempotency(
                    (int) $cached['enrollment_id'],
                    (int) $cached['class_id'],
                    (int) $cached['section_id'],
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

        $this->assertValidPlacement($command, $enrollment->academicYearId);

        $previousClassId = $enrollment->classId;
        $previousSectionId = $enrollment->sectionId;

        $this->unitOfWork->transaction(function () use ($command, $enrollment, $previousClassId, $previousSectionId): void {
            $this->enrollments->updatePlacement(
                $command->enrollmentId,
                $command->classId,
                $command->sectionId,
                $command->specializationId,
            );

            $this->outbox->stage(new EnrollmentPlacementUpdated(
                enrollmentId: $command->enrollmentId,
                studentId: $enrollment->studentId,
                schoolId: $enrollment->schoolId,
                academicYearId: $enrollment->academicYearId,
                previousClassId: $previousClassId,
                previousSectionId: $previousSectionId,
                classId: $command->classId,
                sectionId: $command->sectionId,
                specializationId: $command->specializationId,
                updatedBy: $command->updatedBy,
                occurredAt: new \DateTimeImmutable,
            ));
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_id' => $command->enrollmentId,
                'class_id' => $command->classId,
                'section_id' => $command->sectionId,
            ]);
        }

        return UpdateEnrollmentPlacementResult::success(
            $command->enrollmentId,
            $command->classId,
            $command->sectionId,
        );
    }

    private function assertValidPlacement(UpdateEnrollmentPlacementCommand $command, int $academicYearId): void
    {
        if (! $this->placement->classBelongsToSchool($command->classId, $command->schoolId, $academicYearId)) {
            throw InvalidEnrollmentPlacementException::forReason(
                'Class does not belong to the requested school and academic year.',
            );
        }

        if (! $this->placement->sectionBelongsToClass($command->sectionId, $command->classId)) {
            throw InvalidEnrollmentPlacementException::forReason(
                'Section does not belong to the requested class.',
            );
        }
    }
}
