<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\UpdateEnrollmentPlacementResult;
use App\Application\Enrollment\Services\ApplyEnrollmentPlacementChange;
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
        private readonly ApplyEnrollmentPlacementChange $applyPlacement,
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

        $branchId = $command->updateBranch ? $command->branchId : $enrollment->branchId;
        $departmentId = $command->updateDepartment ? $command->departmentId : $enrollment->departmentId;

        $resultEnrollmentId = $this->unitOfWork->transaction(function () use (
            $command,
            $enrollment,
            $branchId,
            $departmentId,
        ): int {
            $activeEnrollmentId = $this->applyPlacement->apply(
                enrollment: $enrollment,
                classId: $command->classId,
                sectionId: $command->sectionId,
                specializationId: $command->specializationId,
                branchId: $branchId,
                departmentId: $departmentId,
                updatedBy: $command->updatedBy,
                effectiveFrom: $command->effectiveFrom,
                academicYearId: $command->academicYearId,
                effectiveTo: $command->effectiveTo,
                clearEffectiveTo: $command->clearEffectiveTo,
                stageName: $command->stageName,
                updateStage: $command->updateStage,
                syncStudentLabels: true,
            );

            if ($command->gender !== null) {
                $this->enrollments->updateStudentGender($enrollment->studentId, $command->gender);
            }

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
