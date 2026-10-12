<?php

namespace App\Application\Attendance\Support;

use App\Domain\Attendance\Repositories\AttendancePolicyRepositoryInterface;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;

/** The policy that governs a school year now, or the one stamped on an existing sheet. */
final class AttendancePolicyResolver
{
    public function __construct(
        private readonly AttendancePolicyRepositoryInterface $policies,
        private readonly string $defaultTimezone,
    ) {}

    public function current(int $schoolId, int $academicYearId): AttendancePolicy
    {
        return $this->policies->current($schoolId, $academicYearId) ?? AttendancePolicy::defaults($this->defaultTimezone);
    }

    public function forSheet(?int $policyId, int $schoolId, int $academicYearId): AttendancePolicy
    {
        $policy = $policyId !== null ? $this->policies->find($policyId) : null;

        return $policy ?? $this->current($schoolId, $academicYearId);
    }

    public function defaultTimezone(): string
    {
        return $this->defaultTimezone;
    }
}
