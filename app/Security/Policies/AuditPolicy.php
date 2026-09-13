<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\AuditSchoolAccessService;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;

final class AuditPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly AuditSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::AUDIT_VIEW)
            && $this->schoolAccess->canAccessAudit($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::AUDIT_MANAGE)
            && $this->schoolAccess->canAccessAudit($user);
    }
}
