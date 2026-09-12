<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\VocationalSchoolAccessService;

/**
 * Vocational catalog HTTP authorization boundary.
 * Application remains authoritative for school ownership and soft-deactivate lifecycle.
 */
final class VocationalPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly VocationalSchoolAccessService $schoolAccess,
    ) {}

    public function manage(User $user, mixed $record = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::VOCATIONAL_MANAGE)
            && $this->schoolAccess->canAccessVocational($user);
    }

    public function view(User $user, mixed $record = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::VOCATIONAL_VIEW)
            && $this->schoolAccess->canAccessVocational($user);
    }
}
