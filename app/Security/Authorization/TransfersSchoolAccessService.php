<?php

namespace App\Security\Authorization;

final class TransfersSchoolAccessService
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly \App\Security\Context\SchoolContext $schoolContext,
    ) {}

    public function canAccessTransfers(\App\Models\User $user, ?int $targetSchoolId = null): bool
    {
        try {
            $contextSchoolId = $this->schoolContext->requireId();
        } catch (\Throwable) {
            return false;
        }

        if (! in_array($contextSchoolId, $this->schoolScope->allowedSchoolIds($user), true)) {
            return false;
        }

        if ($targetSchoolId === null) {
            return true;
        }

        return $targetSchoolId === $contextSchoolId
            && in_array($targetSchoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }
}
