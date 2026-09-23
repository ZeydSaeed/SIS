<?php

namespace App\Application\Enrollment\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Enrollment\Results\BulkUpdateEnrollmentPlacementResult;
use App\Application\Enrollment\Support\EnrollmentPlacementChange;
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

        if (
            ! $command->updateClass
            && ! $command->updateSection
            && ! $command->updateBranch
            && ! $command->updateDepartment
            && ! $command->updateSpecialization
            && ! $command->updateGender
        ) {
            throw SisDomainException::withCode('enrollment.bulk_placement_empty_fields');
        }

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                /** @var list<int> $cachedIds */
                $cachedIds = array_map('intval', $cached['enrollment_ids'] ?? []);

                return BulkUpdateEnrollmentPlacementResult::fromIdempotency(
                    $cachedIds,
                    (int) ($cached['class_id'] ?? 0),
                    (int) ($cached['section_id'] ?? 0),
                );
            }
        }

        $resolvedClassId = 0;
        $resolvedSectionId = 0;
        $updatedIds = [];
        /** @var list<int> $skippedIds */
        $skippedIds = [];
        /** @var array<int, string> $skipReasons */
        $skipReasons = [];

        $this->unitOfWork->transaction(function () use (
            $command,
            $enrollmentIds,
            &$resolvedClassId,
            &$resolvedSectionId,
            &$updatedIds,
            &$skippedIds,
            &$skipReasons,
        ): void {
            foreach ($enrollmentIds as $enrollmentId) {
                $enrollment = $this->enrollments->findByIdAndSchool($enrollmentId, $command->schoolId);
                if ($enrollment === null) {
                    throw EnrollmentNotFoundException::forId($enrollmentId);
                }

                $updatesPlacement = $command->updateClass
                    || $command->updateSection
                    || $command->updateBranch
                    || $command->updateDepartment
                    || $command->updateSpecialization;

                try {
                    if (! $enrollment->isActive() && ! $command->allowInactive) {
                        // Inactive / cancelled / transferred: never mutate via filter chips.
                        $skippedIds[] = $enrollmentId;
                        $skipReasons[$enrollmentId] = 'التسجيل غير نشط أو ملغي أو منقول';

                        continue;
                    }

                    if ($command->updateGender) {
                        $this->applyGender($command);
                        $this->enrollments->updateStudentGender($enrollment->studentId, (int) $command->gender);
                        if (! $updatesPlacement) {
                            $updatedIds[] = $enrollmentId;
                            $resolvedClassId = $enrollment->classId;
                            $resolvedSectionId = $enrollment->sectionId;

                            continue;
                        }
                    }

                    if (! $updatesPlacement) {
                        continue;
                    }

                    $classId = $command->updateClass
                        ? (int) $command->classId
                        : $enrollment->classId;
                    $sectionId = $command->updateSection
                        ? (int) $command->sectionId
                        : $enrollment->sectionId;

                    if ($command->updateSection && ! $command->updateClass) {
                        $sectionClassId = $this->placement->classIdForSection($sectionId);
                        if ($sectionClassId === null) {
                            throw InvalidEnrollmentPlacementException::forReason(
                                'الشعبة غير موجودة أو غير نشطة',
                            );
                        }
                        $classId = $sectionClassId;
                    }

                    if ($command->updateClass && ! $command->updateSection) {
                        if (! $this->placement->sectionBelongsToClass($sectionId, $classId)) {
                            $sectionId = $this->placement->firstSectionIdForClass($classId);
                            if ($sectionId === null) {
                                throw InvalidEnrollmentPlacementException::forReason(
                                    'الصف لا يحتوي على شعبة نشطة',
                                );
                            }
                        }
                    }

                    if (! $this->placement->classBelongsToSchool(
                        $classId,
                        $command->schoolId,
                        $enrollment->academicYearId,
                    )) {
                        throw InvalidEnrollmentPlacementException::forReason(
                            'الصف لا يتبع المدرسة أو السنة الدراسية للتسجيل',
                        );
                    }

                    if (! $this->placement->sectionBelongsToClass($sectionId, $classId)) {
                        throw InvalidEnrollmentPlacementException::forReason(
                            'الشعبة لا تتبع الصف المطلوب',
                        );
                    }

                    $previousClassId = $enrollment->classId;
                    $previousSectionId = $enrollment->sectionId;
                    $specializationId = $command->updateSpecialization
                        ? $command->specializationId
                        : $enrollment->specializationId;
                    $branchId = $command->updateBranch
                        ? $command->branchId
                        : $enrollment->branchId;
                    $departmentId = $command->updateDepartment
                        ? $command->departmentId
                        : $enrollment->departmentId;

                    $materialChange = EnrollmentPlacementChange::isMaterialChange(
                        $enrollment,
                        $classId,
                        $sectionId,
                        $specializationId,
                        $branchId,
                        $departmentId,
                    );

                    $activeEnrollmentId = $enrollmentId;

                    if ($materialChange && $enrollment->isActive()) {
                        $asOf = EnrollmentPlacementChange::todayIsoDate();
                        $activeEnrollmentId = $this->enrollments->supersedeWithNewPlacement(
                            current: $enrollment,
                            classId: $classId,
                            sectionId: $sectionId,
                            specializationId: $specializationId,
                            branchId: $branchId,
                            departmentId: $departmentId,
                            effectiveTo: $asOf,
                            effectiveFrom: $asOf,
                            enrolledBy: $command->updatedBy,
                        );
                    } else {
                        $this->enrollments->updatePlacement(
                            $enrollmentId,
                            $classId,
                            $sectionId,
                            $specializationId,
                            $branchId,
                            $departmentId,
                        );
                    }

                    $resolvedClassId = $classId;
                    $resolvedSectionId = $sectionId;
                    $updatedIds[] = $activeEnrollmentId;

                    $this->outbox->stage(new EnrollmentPlacementUpdated(
                        enrollmentId: $activeEnrollmentId,
                        studentId: $enrollment->studentId,
                        schoolId: $enrollment->schoolId,
                        academicYearId: $enrollment->academicYearId,
                        previousClassId: $previousClassId,
                        previousSectionId: $previousSectionId,
                        classId: $classId,
                        sectionId: $sectionId,
                        specializationId: $specializationId,
                        updatedBy: $command->updatedBy,
                        occurredAt: new \DateTimeImmutable,
                        previousEnrollmentId: $materialChange && $enrollment->isActive()
                            ? $enrollmentId
                            : null,
                        newEnrollmentId: $materialChange && $enrollment->isActive()
                            ? $activeEnrollmentId
                            : null,
                    ));
                } catch (InvalidEnrollmentPlacementException $exception) {
                    $skippedIds[] = $enrollmentId;
                    $skipReasons[$enrollmentId] = $exception->getMessage();
                }
            }

            if ($updatedIds === []) {
                if ($skippedIds !== []) {
                    $firstId = $skippedIds[0];
                    $reason = $skipReasons[$firstId] ?? 'تعذر تحديث التسجيلات المحددة';

                    throw InvalidEnrollmentPlacementException::forReason(
                        "لم يتم تحديث أي تسجيل. مثال (#{$firstId}): {$reason}",
                    );
                }

                throw EnrollmentNotActiveException::forId($enrollmentIds[0]);
            }
        });

        $updatedIds = array_values(array_unique($updatedIds));
        $skippedIds = array_values(array_unique($skippedIds));

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'enrollment_ids' => $updatedIds,
                'class_id' => $resolvedClassId,
                'section_id' => $resolvedSectionId,
            ]);
        }

        return BulkUpdateEnrollmentPlacementResult::success(
            $updatedIds,
            $resolvedClassId,
            $resolvedSectionId,
            $skippedIds,
            $skipReasons,
        );
    }

    private function applyGender(BulkUpdateEnrollmentPlacementCommand $command): void
    {
        $gender = (int) $command->gender;
        if ($gender !== 1 && $gender !== 2) {
            throw InvalidEnrollmentPlacementException::forReason(
                'قيمة الجنس غير صحيحة',
            );
        }
    }
}
