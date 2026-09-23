<?php

namespace App\Domain\Enrollment\Services;

use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Student\ValueObjects\StudentStatus;

/**
 * Maps identity (student) status ↔ year placement (enrollment) status.
 * Codes are intentionally not 1:1 — Suspended and Inactive both close placement.
 */
final class StudentEnrollmentStatusSyncPolicy
{
    public function enrollmentStatusFor(StudentStatus $studentStatus): int
    {
        return match ($studentStatus) {
            StudentStatus::Active => EnrollmentStatus::ACTIVE,
            StudentStatus::Inactive, StudentStatus::Suspended, StudentStatus::Graduated => EnrollmentStatus::INACTIVE,
            StudentStatus::Withdrawn => EnrollmentStatus::CANCELLED,
        };
    }

    public function studentStatusFor(int $enrollmentStatus): ?StudentStatus
    {
        return match ($enrollmentStatus) {
            EnrollmentStatus::ACTIVE => StudentStatus::Active,
            EnrollmentStatus::INACTIVE => StudentStatus::Inactive,
            EnrollmentStatus::CANCELLED, EnrollmentStatus::DISMISSED => StudentStatus::Withdrawn,
            EnrollmentStatus::TRANSFERRED => StudentStatus::Inactive,
            default => null, // SUPERSEDED and unknown — no student mutation
        };
    }
}
