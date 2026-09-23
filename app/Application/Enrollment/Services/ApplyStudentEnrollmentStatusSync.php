<?php

namespace App\Application\Enrollment\Services;

use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\Services\StudentEnrollmentStatusSyncPolicy;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Student\Repositories\StudentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentStatus;

/**
 * Applies bidirectional student ↔ enrollment status sync inside the caller's transaction.
 */
final class ApplyStudentEnrollmentStatusSync
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly StudentRepositoryInterface $students,
        private readonly StudentEnrollmentStatusSyncPolicy $policy,
    ) {}

    /**
     * After student status change — align operable enrollment rows.
     *
     * @param  list<int>  $studentIds
     */
    public function syncEnrollmentsFromStudent(
        int $schoolId,
        array $studentIds,
        StudentStatus $studentStatus,
        string $effectiveTo,
    ): void {
        $targetEnrollmentStatus = $this->policy->enrollmentStatusFor($studentStatus);

        foreach ($studentIds as $studentId) {
            if ($studentId <= 0) {
                continue;
            }

            $rows = $this->enrollments->listOperableForStudent($schoolId, $studentId);
            foreach ($rows as $enrollment) {
                $this->applyEnrollmentTarget($enrollment, $targetEnrollmentStatus, $effectiveTo);
            }
        }
    }

    /**
     * After enrollment status change — align the student identity status.
     */
    public function syncStudentFromEnrollment(
        int $schoolId,
        int $studentId,
        int $enrollmentStatus,
    ): void {
        $target = $this->policy->studentStatusFor($enrollmentStatus);
        if ($target === null) {
            return;
        }

        $student = $this->students->findByIdForSchool($studentId, $schoolId);
        if ($student === null) {
            return;
        }

        if ($student->status() === $target) {
            return;
        }

        $this->students->updateStatus($studentId, $target->value);
    }

    private function applyEnrollmentTarget(
        EnrollmentSnapshot $enrollment,
        int $targetStatus,
        string $effectiveTo,
    ): void {
        if ($targetStatus === EnrollmentStatus::ACTIVE) {
            if ($enrollment->isActive()) {
                return;
            }

            if ($this->enrollments->hasActiveEnrollment($enrollment->studentId, $enrollment->academicYearId)) {
                return;
            }

            $this->enrollments->reopen($enrollment->id);

            return;
        }

        if ($enrollment->status === $targetStatus && ! $enrollment->isActive()) {
            return;
        }

        $to = $effectiveTo < $enrollment->effectiveFrom ? $enrollment->effectiveFrom : $effectiveTo;

        if ($targetStatus === EnrollmentStatus::INACTIVE) {
            if ($enrollment->isActive()) {
                $this->enrollments->deactivate($enrollment->id, $to);
            } else {
                $this->enrollments->setClosedStatus($enrollment->id, EnrollmentStatus::INACTIVE, $to);
            }

            return;
        }

        if ($targetStatus === EnrollmentStatus::CANCELLED) {
            if ($enrollment->isActive()) {
                $this->enrollments->cancel($enrollment->id, $to);
            } else {
                $this->enrollments->setClosedStatus($enrollment->id, EnrollmentStatus::CANCELLED, $to);
            }
        }
    }
}
