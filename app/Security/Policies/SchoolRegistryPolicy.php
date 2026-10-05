<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;

final class SchoolRegistryPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    /** Add schools / open the registry — requires a resolved school context (roles are copied from it). */
    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ORGANIZATION_SCHOOLS_MANAGE)
            && $this->schoolContext->id() !== null;
    }

    /** Edit a school — only schools the user is linked to (tenant isolation). */
    public function update(User $user, int $schoolId): bool
    {
        return $this->manage($user)
            && in_array($schoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }
}
