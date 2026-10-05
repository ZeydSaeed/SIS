<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Context\SchoolContext;

final class DirectorateRegistryPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly SchoolContext $schoolContext,
    ) {}

    /** Directorates are shared reference data — add/edit needs its own permission plus a school context. */
    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ORGANIZATION_DIRECTORATES_MANAGE)
            && $this->schoolContext->id() !== null;
    }
}
