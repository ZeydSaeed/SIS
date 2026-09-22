<?php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;

/**
 * Reopen enrollment preconditions — keeps Application handler within ARCH-103.
 */
final class ReopenEnrollmentGuard
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
    ) {}

    /**
     * @return list<string>|null  Failure codes, or null when reopen is allowed.
     */
    public function failureCodes(?EnrollmentSnapshot $enrollment): ?array
    {
        if ($enrollment === null) {
            return ['enrollment.not_found'];
        }

        if ($enrollment->isActive()) {
            return ['enrollment.already_open'];
        }

        if (! EnrollmentStatus::isReopenable($enrollment->status)) {
            return ['enrollment.not_reopenable'];
        }

        if ($this->enrollments->hasActiveEnrollment($enrollment->studentId, $enrollment->academicYearId)) {
            return ['enrollment.student_has_active_enrollment'];
        }

        return null;
    }
}
