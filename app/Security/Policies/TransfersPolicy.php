<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\TransfersSchoolAccessService;

final class TransfersPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly TransfersSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TRANSFERS_VIEW)
            && $this->schoolAccess->canAccessTransfers($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TRANSFERS_MANAGE)
            && $this->schoolAccess->canAccessTransfers($user);
    }
}
