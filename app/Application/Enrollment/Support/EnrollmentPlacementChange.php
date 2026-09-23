<?php

namespace App\Application\Enrollment\Support;

use App\Domain\Enrollment\Data\EnrollmentSnapshot;

/** Shared helpers for mid-year placement history (close + create). */
final class EnrollmentPlacementChange
{
    public static function todayIsoDate(): string
    {
        return (new \DateTimeImmutable('today'))->format('Y-m-d');
    }

    public static function isMaterialChange(
        EnrollmentSnapshot $current,
        int $classId,
        int $sectionId,
        ?int $specializationId,
        ?int $branchId,
        ?int $departmentId,
    ): bool {
        return $current->classId !== $classId
            || $current->sectionId !== $sectionId
            || $current->specializationId !== $specializationId
            || $current->branchId !== $branchId
            || $current->departmentId !== $departmentId;
    }
}
