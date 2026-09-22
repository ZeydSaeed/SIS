<?php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Shared\Exceptions\SisDomainException;

/**
 * Bulk enrollment status preconditions — keeps Application handler within ARCH-103.
 */
final class BulkEnrollmentStatusGuard
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function uniqueIds(array $ids): array
    {
        $unique = [];
        foreach ($ids as $id) {
            if ($id > 0) {
                $unique[$id] = $id;
            }
        }

        $values = array_values($unique);
        if ($values === []) {
            throw SisDomainException::withCode('enrollment.bulk_status_empty');
        }

        return $values;
    }

    public function assertTargetStatus(int $status): void
    {
        if (! in_array($status, EnrollmentStatus::all(), true)) {
            throw SisDomainException::withCode('enrollment.invalid_status');
        }
    }

    public function requireForSchool(int $enrollmentId, int $schoolId): EnrollmentSnapshot
    {
        $enrollment = $this->enrollments->findByIdAndSchool($enrollmentId, $schoolId);
        if ($enrollment === null) {
            throw EnrollmentNotFoundException::forId($enrollmentId);
        }

        return $enrollment;
    }

    public function assertCanActivate(EnrollmentSnapshot $enrollment): bool
    {
        if ($enrollment->isActive()) {
            return false;
        }

        if ($this->enrollments->hasActiveEnrollment($enrollment->studentId, $enrollment->academicYearId)) {
            throw SisDomainException::withCode('enrollment.student_has_active_enrollment');
        }

        return true;
    }

    public function assertCanCloseActive(EnrollmentSnapshot $enrollment, string $effectiveTo): void
    {
        if (! $enrollment->isActive()) {
            throw SisDomainException::withCode('enrollment.not_active');
        }

        if ($effectiveTo < $enrollment->effectiveFrom) {
            throw SisDomainException::withCode('enrollment.invalid_effective_to');
        }
    }
}
