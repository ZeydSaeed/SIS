<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\CurriculumSchoolAccessService;
use App\Security\Authorization\Permission;

final class CurriculumPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly CurriculumSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::CURRICULUM_VIEW)
            && $this->schoolAccess->canAccessCurriculum($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::CURRICULUM_MANAGE)
            && $this->schoolAccess->canAccessCurriculum($user);
    }
}
