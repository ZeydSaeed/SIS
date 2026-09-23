<?php

namespace App\Application\Enrollment\Services;

use App\Application\Contracts\OutboxRepository;
use App\Application\Enrollment\Support\EnrollmentPlacementChange;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Events\EnrollmentPlacementUpdated;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;

/**
 * Applies class/section/branch/department placement with mid-year history
 * (supersede + new active row) — extracted for ARCH-101/103.
 */
final class ApplyEnrollmentPlacementChange
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly OutboxRepository $outbox,
    ) {}

    /**
     * @return int Active enrollment id after the change (may be a new row)
     */
    public function apply(
        EnrollmentSnapshot $enrollment,
        int $classId,
        int $sectionId,
        ?int $specializationId,
        ?int $branchId,
        ?int $departmentId,
        ?int $updatedBy,
        ?string $effectiveFrom = null,
        ?int $academicYearId = null,
        ?string $effectiveTo = null,
        bool $clearEffectiveTo = false,
        ?string $stageName = null,
        bool $updateStage = false,
        bool $syncStudentLabels = false,
    ): int {
        $previousClassId = $enrollment->classId;
        $previousSectionId = $enrollment->sectionId;
        $materialChange = EnrollmentPlacementChange::isMaterialChange(
            $enrollment,
            $classId,
            $sectionId,
            $specializationId,
            $branchId,
            $departmentId,
        );

        $activeEnrollmentId = $enrollment->id;
        $didSupersede = false;

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
                enrolledBy: $updatedBy,
            );
            $didSupersede = true;
        } else {
            $this->enrollments->updatePlacement(
                $enrollment->id,
                $classId,
                $sectionId,
                $specializationId,
                $branchId,
                $departmentId,
                $effectiveFrom,
                $academicYearId,
                $effectiveTo,
                $clearEffectiveTo,
                $stageName,
                $updateStage,
                $syncStudentLabels,
            );
        }

        $this->outbox->stage(new EnrollmentPlacementUpdated(
            enrollmentId: $activeEnrollmentId,
            studentId: $enrollment->studentId,
            schoolId: $enrollment->schoolId,
            academicYearId: $academicYearId ?? $enrollment->academicYearId,
            previousClassId: $previousClassId,
            previousSectionId: $previousSectionId,
            classId: $classId,
            sectionId: $sectionId,
            specializationId: $specializationId,
            updatedBy: $updatedBy,
            occurredAt: new \DateTimeImmutable,
            previousEnrollmentId: $didSupersede ? $enrollment->id : null,
            newEnrollmentId: $didSupersede ? $activeEnrollmentId : null,
        ));

        return $activeEnrollmentId;
    }
}
