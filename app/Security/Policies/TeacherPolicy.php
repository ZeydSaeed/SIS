<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\TeachersSchoolAccessService;

final class TeacherPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly TeachersSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TEACHERS_VIEW)
            && $this->schoolAccess->canAccessTeachers($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TEACHERS_MANAGE)
            && $this->schoolAccess->canAccessTeachers($user);
    }
}
