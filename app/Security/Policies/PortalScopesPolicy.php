<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\ResultsSchoolAccessService;

/**
 * Admin portal party-scope link/unlink/list authorization.
 */
final class PortalScopesPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly ResultsSchoolAccessService $schoolAccess,
    ) {}

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::PORTAL_SCOPES_MANAGE)
            && $this->schoolAccess->canAccessResults($user);
    }
}
