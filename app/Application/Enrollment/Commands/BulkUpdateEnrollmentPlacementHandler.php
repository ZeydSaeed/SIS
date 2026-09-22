<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\BulkUpdateEnrollmentPlacementResult;
use App\Domain\Enrollment\Events\EnrollmentPlacementUpdated;
use App\Domain\Enrollment\Exceptions\EnrollmentNotActiveException;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Enrollment\Exceptions\InvalidEnrollmentPlacementException;
use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Shared\Exceptions\SisDomainException;

final class BulkUpdateEnrollmentPlacementHandler implements CommandHandler
{
    private const COMMAND_NAME = 'BulkUpdateEnrollmentPlacement';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly EnrollmentPlacementRepositoryInterface $placement,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): BulkUpdateEnrollmentPlacementResult
    {
        assert($command instanceof BulkUpdateEnrollmentPlacementCommand);

        $enrollmentIds = array_values(array_unique(array_filter(
            $command->enrollmentIds,
            static fn (int $id): bool => $id > 0,
        )));

        if ($enrollmentIds === []) {
            throw SisDomainException::withCode('enrollment.bulk_placement_empty');
        }

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                /** @var list<int> $cachedIds */
                $cachedIds = array_map('intval', $cached['enrollment_ids'] ?? []);

                return BulkUpdateEnrollmentPlacementResult::fromIdempotency(
                    $cachedIds,
                    (int) $cached['class_id'],
                    (int) $cached['section_id'],
                );
            }
        }

        $this->unitOfWork->transaction(function () use ($command, $enrollmentIds): void {
            foreach ($enrollmentIds as $enrollmentId) {
                $enrollment = $this->enrollments->findByIdAndSchool($enrollmentId, $command->schoolId);
                if ($enrollment === null) {
                    throw EnrollmentNotFoundException::forId($enrollmentId);
                }

                if (! $enrollment->isActive()) {
                    throw EnrollmentNotActiveException::forId($enrollmentId);
                }

                if (! $this->placement->classBelongsToSchool(
                    $command->classId,
                    $command->schoolId,
                    $enrollment->academicYearId,
                )) {
                    throw InvalidEnrollmentPlacementException::forReason(
                        'Class does not belong to the requested school and academic year.',
                    );
                }

                if (! $this->placement->sectionBelongsToClass($command->sectionId, $command->classId)) {
                    throw InvalidEnrollmentPlacementException::forReason(
                        'Section does not belong to the requested class.',
                    );
                }

                $previousClassId = $enrollment->classId;
                $previousSectionId = $enrollment->sectionId;

                $this->enrollments->updatePlacement(
                    $enrollmentId,
                    $command->classId,
                    $command->sectionId,
                    $command->specializationId,
                    $command->branchId,
                    $command->departmentId,
                );

                $this->outbox->stage(new EnrollmentPlacementUpdated(
                    enrollmentId: $enrollmentId,
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
            }
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_ids' => $enrollmentIds,
                'class_id' => $command->classId,
                'section_id' => $command->sectionId,
            ]);
        }

        return BulkUpdateEnrollmentPlacementResult::success(
            $enrollmentIds,
            $command->classId,
            $command->sectionId,
        );
    }
}
