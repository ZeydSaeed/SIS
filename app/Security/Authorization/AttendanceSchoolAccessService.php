<?php

namespace App\Security\Authorization;

use App\Models\User;
use App\Security\Context\SchoolContext;

/**
 * School-scope gate for Attendance authorization (no Attendance Eloquent model in R1 security package).
 */
final class AttendanceSchoolAccessService
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function canAccessAttendance(User $user, ?int $targetSchoolId = null): bool
    {
        if (! $this->hasValidSchoolContext($user)) {
            return false;
        }

        $contextSchoolId = $this->schoolContext->requireId();

        if (! in_array($contextSchoolId, $this->schoolScope->allowedSchoolIds($user), true)) {
            return false;
        }

        if ($targetSchoolId === null) {
            return true;
        }

        return $targetSchoolId === $contextSchoolId
            && in_array($targetSchoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }

    private function hasValidSchoolContext(User $user): bool
    {
        try {
            $schoolId = $this->schoolContext->requireId();
        } catch (\Throwable) {
            return false;
        }

        return in_array($schoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }
}
