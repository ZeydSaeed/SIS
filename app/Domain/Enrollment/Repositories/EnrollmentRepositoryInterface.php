<?php

namespace App\Domain\Enrollment\Repositories;

use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;

interface EnrollmentRepositoryInterface
{
    public function hasActiveEnrollment(int $studentId, int $academicYearId): bool;

    public function findById(int $enrollmentId): ?EnrollmentSnapshot;

    public function findByIdAndSchool(int $enrollmentId, int $schoolId): ?EnrollmentSnapshot;

    public function save(CreateEnrollmentData $data): int;

    public function updatePlacement(
        int $enrollmentId,
        int $classId,
        int $sectionId,
        ?int $specializationId,
        ?int $branchId = null,
        ?int $departmentId = null,
        ?string $effectiveFrom = null,
        ?int $academicYearId = null,
        ?string $effectiveTo = null,
        bool $clearEffectiveTo = false,
        ?string $stageName = null,
        bool $updateStage = false,
        bool $syncStudentLabels = false,
    ): void;

    public function updateStudentGender(int $studentId, int $gender): void;

    public function cancel(int $enrollmentId, string $effectiveTo): void;

    public function deactivate(int $enrollmentId, string $effectiveTo): void;

    public function reopen(int $enrollmentId): bool;

    public function setClosedStatus(int $enrollmentId, int $status, string $effectiveTo): void;

    public function closeAsTransferred(int $enrollmentId, int $schoolId, string $effectiveTo): bool;

    /**
     * Non-superseded placement rows for a student in a school (all years).
     *
     * @return list<EnrollmentSnapshot>
     */
    public function listOperableForStudent(int $schoolId, int $studentId): array;

    /**
     * Close the current active placement (status=superseded + effective_to)
     * and insert a new active enrollment row for the same student/year.
     *
     * @return int New enrollment id
     */
    public function supersedeWithNewPlacement(
        EnrollmentSnapshot $current,
        int $classId,
        int $sectionId,
        ?int $specializationId,
        ?int $branchId,
        ?int $departmentId,
        string $effectiveTo,
        string $effectiveFrom,
        ?int $enrolledBy,
    ): int;

    public function generateEnrollmentNumber(int $schoolId, int $academicYearId): string;
}
