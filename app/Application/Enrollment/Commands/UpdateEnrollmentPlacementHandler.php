<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\UpdateEnrollmentPlacementResult;
use App\Application\Enrollment\Support\EnrollmentPlacementChange;
use App\Domain\Enrollment\Events\EnrollmentPlacementUpdated;
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

        $yearForPlacement = $command->academicYearId ?? $enrollment->academicYearId;
        $this->assertValidPlacement($command, $yearForPlacement);

        $previousClassId = $enrollment->classId;
        $previousSectionId = $enrollment->sectionId;
        $branchId = $command->updateBranch ? $command->branchId : $enrollment->branchId;
        $departmentId = $command->updateDepartment ? $command->departmentId : $enrollment->departmentId;

        $resultEnrollmentId = $this->unitOfWork->transaction(function () use (
            $command,
            $enrollment,
            $previousClassId,
            $previousSectionId,
            $branchId,
            $departmentId,
        ): int {
            $materialChange = EnrollmentPlacementChange::isMaterialChange(
                $enrollment,
                $command->classId,
                $command->sectionId,
                $command->specializationId,
                $branchId,
                $departmentId,
            );

            $activeEnrollmentId = $enrollment->id;

            if ($materialChange && $enrollment->isActive()) {
                $asOf = EnrollmentPlacementChange::todayIsoDate();
                $activeEnrollmentId = $this->enrollments->supersedeWithNewPlacement(
                    current: $enrollment,
                    classId: $command->classId,
                    sectionId: $command->sectionId,
                    specializationId: $command->specializationId,
                    branchId: $branchId,
                    departmentId: $departmentId,
                    effectiveTo: $asOf,
                    effectiveFrom: $asOf,
                    enrolledBy: $command->updatedBy,
                );
            } else {
                $this->enrollments->updatePlacement(
                    $command->enrollmentId,
                    $command->classId,
                    $command->sectionId,
                    $command->specializationId,
                    $branchId,
                    $departmentId,
                    $command->effectiveFrom,
                    $command->academicYearId,
                    $command->effectiveTo,
                    $command->clearEffectiveTo,
                    $command->stageName,
                    $command->updateStage,
                    syncStudentLabels: true,
                );
            }

            if ($command->gender !== null) {
                $this->enrollments->updateStudentGender($enrollment->studentId, $command->gender);
            }

            $this->outbox->stage(new EnrollmentPlacementUpdated(
                enrollmentId: $activeEnrollmentId,
                studentId: $enrollment->studentId,
                schoolId: $enrollment->schoolId,
                academicYearId: $command->academicYearId ?? $enrollment->academicYearId,
                previousClassId: $previousClassId,
                previousSectionId: $previousSectionId,
                classId: $command->classId,
                sectionId: $command->sectionId,
                specializationId: $command->specializationId,
                updatedBy: $command->updatedBy,
                occurredAt: new \DateTimeImmutable,
                previousEnrollmentId: $materialChange && $enrollment->isActive()
                    ? $enrollment->id
                    : null,
                newEnrollmentId: $materialChange && $enrollment->isActive()
                    ? $activeEnrollmentId
                    : null,
            ));

            return $activeEnrollmentId;
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_id' => $resultEnrollmentId,
                'class_id' => $command->classId,
                'section_id' => $command->sectionId,
            ]);
        }

        return UpdateEnrollmentPlacementResult::success(
            $resultEnrollmentId,
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
