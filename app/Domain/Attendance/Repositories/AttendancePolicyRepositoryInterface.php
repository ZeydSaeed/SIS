<?php

namespace App\Domain\Attendance\Repositories;

use App\Domain\Attendance\ValueObjects\AttendancePolicy;

interface AttendancePolicyRepositoryInterface
{
    /** The policy in force now (effective_to IS NULL), or null when the school never saved one. */
    public function current(int $schoolId, int $academicYearId): ?AttendancePolicy;

    public function find(int $policyId): ?AttendancePolicy;

    /**
     * Closes the current version (effective_to = now) and inserts the next one. Earlier versions stay — sessions
     * keep pointing at the version that governed them.
     */
    public function saveNewVersion(int $schoolId, int $academicYearId, AttendancePolicy $values, ?int $createdBy): int;
}
