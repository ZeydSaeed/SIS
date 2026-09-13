<?php

namespace App\Security\Authorization;

final class CurriculumSchoolAccessService
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly \App\Security\Context\SchoolContext $schoolContext,
    ) {}

    public function canAccessCurriculum(\App\Models\User $user): bool
    {
        try {
            $contextSchoolId = $this->schoolContext->requireId();
        } catch (\Throwable) {
            return false;
        }

        return in_array($contextSchoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }
}
