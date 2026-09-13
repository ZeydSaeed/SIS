<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\HrSchoolAccessService;
use App\Security\Authorization\Permission;

/**
 * Payroll AuthZ boundary (Phase 10).
 * No payroll HTTP/commands until schema ballot — Gate abilities only.
 */
final class HrPayrollPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly HrSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::HR_PAYROLL_VIEW)
            && $this->schoolAccess->canAccessHr($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::HR_PAYROLL_MANAGE)
            && $this->schoolAccess->canAccessHr($user);
    }
}
