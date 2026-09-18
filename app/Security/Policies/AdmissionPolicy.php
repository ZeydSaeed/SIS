<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\AdmissionSchoolAccessService;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;

final class AdmissionPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly AdmissionSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ADMISSION_VIEW)
            && $this->schoolAccess->canAccessAdmission($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ADMISSION_MANAGE)
            && $this->schoolAccess->canAccessAdmission($user);
    }
}
