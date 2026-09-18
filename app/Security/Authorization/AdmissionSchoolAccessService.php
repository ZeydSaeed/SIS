<?php

namespace App\Security\Authorization;

use App\Models\User;
use App\Security\Context\SchoolContext;

final class AdmissionSchoolAccessService
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function canAccessAdmission(User $user): bool
    {
        try {
            $schoolId = $this->schoolContext->requireId();
        } catch (\Throwable) {
            return false;
        }

        return in_array($schoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }
}
