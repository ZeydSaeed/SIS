<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\CommunicationSchoolAccessService;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;

final class CommunicationPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly CommunicationSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::COMMUNICATION_VIEW)
            && $this->schoolAccess->canAccessCommunication($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::COMMUNICATION_MANAGE)
            && $this->schoolAccess->canAccessCommunication($user);
    }
}
