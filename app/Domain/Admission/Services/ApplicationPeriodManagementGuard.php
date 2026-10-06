<?php

namespace App\Domain\Admission\Services;

use App\Domain\Admission\Exceptions\ApplicationPeriodAcademicYearLockedException;
use App\Domain\Admission\Exceptions\ApplicationPeriodOutOfScopeException;

/**
 * Who may manage an existing admission period, and what may change on it.
 *
 * - A period of a directorate is managed only by users linked to that directorate's schools.
 *   Legacy shared periods (no directorate) stay manageable by every admission manager.
 * - The academic year is fixed after creation (applications inherit it from the period).
 */
final class ApplicationPeriodManagementGuard
{
    /**
     * @param  array<string, mixed>  $period
     * @param  list<int>|null  $allowedDirectorateIds  Null = unrestricted (system callers).
     */
    public function assertManageable(array $period, ?array $allowedDirectorateIds): void
    {
        $directorateId = isset($period['directorate_id']) ? (int) $period['directorate_id'] : null;
        if ($allowedDirectorateIds === null || $directorateId === null) {
            return;
        }

        if (! in_array($directorateId, $allowedDirectorateIds, true)) {
            throw ApplicationPeriodOutOfScopeException::forPeriod((int) $period['id']);
        }
    }

    /**
     * @param  array<string, mixed>  $period
     */
    public function assertAcademicYearUnchanged(array $period, int $academicYearId): void
    {
        if ((int) $period['academic_year_id'] !== $academicYearId) {
            throw ApplicationPeriodAcademicYearLockedException::forPeriod((int) $period['id']);
        }
    }
}
