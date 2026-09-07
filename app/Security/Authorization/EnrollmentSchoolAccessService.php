<?php

namespace App\Security\Authorization;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Models\User;
use App\Security\Context\SchoolContext;

final class EnrollmentSchoolAccessService
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function canAccessEnrollment(User $user, EnrollmentRecord|string|int|null $enrollment = null): bool
    {
        if (! $this->hasValidSchoolContext($user)) {
            return false;
        }

        $contextSchoolId = $this->schoolContext->requireId();

        if (! in_array($contextSchoolId, $this->schoolScope->allowedSchoolIds($user), true)) {
            return false;
        }

        if ($enrollment === null || $enrollment === EnrollmentRecord::class) {
            return true;
        }

        $record = $this->resolveEnrollment($enrollment);
        if ($record === null) {
            return false;
        }

        return (int) $record->school_id === $contextSchoolId;
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

    private function resolveEnrollment(EnrollmentRecord|string|int $enrollment): ?EnrollmentRecord
    {
        if ($enrollment instanceof EnrollmentRecord) {
            return $enrollment;
        }

        if (is_int($enrollment) || (is_string($enrollment) && ctype_digit($enrollment))) {
            return EnrollmentRecord::query()->find((int) $enrollment);
        }

        return null;
    }
}
