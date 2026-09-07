<?php

namespace App\Security\Authorization\Contracts;

use App\Models\User;

interface AuthorizationServiceInterface
{
    public function userHasPermission(User $user, string $permissionCode): bool;

    /**
     * @return list<string>
     */
    public function permissionsFor(User $user): array;
}
