<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\FinanceSchoolAccessService;
use App\Security\Authorization\Permission;

final class FinancePolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly FinanceSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::FINANCE_VIEW)
            && $this->schoolAccess->canAccessFinance($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::FINANCE_MANAGE)
            && $this->schoolAccess->canAccessFinance($user);
    }
}
