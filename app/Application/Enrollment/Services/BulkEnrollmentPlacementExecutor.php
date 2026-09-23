<?php

namespace App\Application\Enrollment\Services;

use App\Application\Enrollment\Commands\BulkUpdateEnrollmentPlacementCommand;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Exceptions\EnrollmentNotActiveException;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Enrollment\Exceptions\InvalidEnrollmentPlacementException;
use App\Domain\Enrollment\Repositories\EnrollmentPlacementRepositoryInterface;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Bulk placement loop body — extracted from handler for ARCH-101/103.
 */
final class BulkEnrollmentPlacementExecutor
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly EnrollmentPlacementRepositoryInterface $placement,
        private readonly ApplyEnrollmentPlacementChange $applyPlacement,
    ) {}

    /**
     * @return list<int>
     */
    public function prepareIds(BulkUpdateEnrollmentPlacementCommand $command): array
    {
        $enrollmentIds = array_values(array_unique(array_filter(
            $command->enrollmentIds,
            static fn (int $id): bool => $id > 0,
        )));

        if ($enrollmentIds === []) {
            throw SisDomainException::withCode('enrollment.bulk_placement_empty');
        }

        $hasField = $command->updateClass
            || $command->updateSection
            || $command->updateBranch
            || $command->updateDepartment
            || $command->updateSpecialization
            || $command->updateGender;

        if (! $hasField) {
            throw SisDomainException::withCode('enrollment.bulk_placement_empty_fields');
        }

        return $enrollmentIds;
    }

    /**
     * @param  list<int>  $enrollmentIds
     * @return array{
     *     updatedIds: list<int>,
     *     skippedIds: list<int>,
     *     skipReasons: array<int, string>,
     *     resolvedClassId: int,
     *     resolvedSectionId: int
     * }
     */
    public function applyAll(BulkUpdateEnrollmentPlacementCommand $command, array $enrollmentIds): array
    {
        $resolvedClassId = 0;
        $resolvedSectionId = 0;
        $updatedIds = [];
        /** @var list<int> $skippedIds */
        $skippedIds = [];
        /** @var array<int, string> $skipReasons */
        $skipReasons = [];

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
                $row = $this->applyOne($command, $enrollment, $updatesPlacement);
                if ($row === null) {
                    $skippedIds[] = $enrollmentId;
                    $skipReasons[$enrollmentId] = 'التسجيل غير نشط أو ملغي أو منقول';

                    continue;
                }

                if ($row['skipped']) {
                    continue;
                }

                $resolvedClassId = $row['classId'];
                $resolvedSectionId = $row['sectionId'];
                $updatedIds[] = $row['enrollmentId'];
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

        return [
            'updatedIds' => $updatedIds,
            'skippedIds' => $skippedIds,
            'skipReasons' => $skipReasons,
            'resolvedClassId' => $resolvedClassId,
            'resolvedSectionId' => $resolvedSectionId,
        ];
    }

    /**
     * @return array{enrollmentId: int, classId: int, sectionId: int, skipped: bool}|null
     *         null = inactive skip
     */
    private function applyOne(
        BulkUpdateEnrollmentPlacementCommand $command,
        EnrollmentSnapshot $enrollment,
        bool $updatesPlacement,
    ): ?array {
        if (! $enrollment->isActive() && ! $command->allowInactive) {
            return null;
        }

        if ($command->updateGender) {
            $this->assertGender($command);
            $this->enrollments->updateStudentGender($enrollment->studentId, (int) $command->gender);
            if (! $updatesPlacement) {
                return [
                    'enrollmentId' => $enrollment->id,
                    'classId' => $enrollment->classId,
                    'sectionId' => $enrollment->sectionId,
                    'skipped' => false,
                ];
            }
        }

        if (! $updatesPlacement) {
            return [
                'enrollmentId' => $enrollment->id,
                'classId' => $enrollment->classId,
                'sectionId' => $enrollment->sectionId,
                'skipped' => true,
            ];
        }

        [$classId, $sectionId] = $this->resolveClassAndSection($command, $enrollment);
        $this->assertPlacementBelongs($command->schoolId, $enrollment->academicYearId, $classId, $sectionId);

        $specializationId = $command->updateSpecialization
            ? $command->specializationId
            : $enrollment->specializationId;
        $branchId = $command->updateBranch ? $command->branchId : $enrollment->branchId;
        $departmentId = $command->updateDepartment ? $command->departmentId : $enrollment->departmentId;

        $activeEnrollmentId = $this->applyPlacement->apply(
            enrollment: $enrollment,
            classId: $classId,
            sectionId: $sectionId,
            specializationId: $specializationId,
            branchId: $branchId,
            departmentId: $departmentId,
            updatedBy: $command->updatedBy,
        );

        return [
            'enrollmentId' => $activeEnrollmentId,
            'classId' => $classId,
            'sectionId' => $sectionId,
            'skipped' => false,
        ];
    }

    /** @return array{0: int, 1: int} */
    private function resolveClassAndSection(
        BulkUpdateEnrollmentPlacementCommand $command,
        EnrollmentSnapshot $enrollment,
    ): array {
        $classId = $command->updateClass ? (int) $command->classId : $enrollment->classId;
        $sectionId = $command->updateSection ? (int) $command->sectionId : $enrollment->sectionId;

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

        return [$classId, $sectionId];
    }

    private function assertPlacementBelongs(
        int $schoolId,
        int $academicYearId,
        int $classId,
        int $sectionId,
    ): void {
        if (! $this->placement->classBelongsToSchool($classId, $schoolId, $academicYearId)) {
            throw InvalidEnrollmentPlacementException::forReason(
                'الصف لا يتبع المدرسة أو السنة الدراسية للتسجيل',
            );
        }

        if (! $this->placement->sectionBelongsToClass($sectionId, $classId)) {
            throw InvalidEnrollmentPlacementException::forReason(
                'الشعبة لا تتبع الصف المطلوب',
            );
        }
    }

    private function assertGender(BulkUpdateEnrollmentPlacementCommand $command): void
    {
        $gender = (int) $command->gender;
        if ($gender !== 1 && $gender !== 2) {
            throw InvalidEnrollmentPlacementException::forReason(
                'قيمة الجنس غير صحيحة',
            );
        }
    }
}
