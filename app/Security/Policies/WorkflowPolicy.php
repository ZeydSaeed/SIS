<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\WorkflowSchoolAccessService;

final class WorkflowPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly WorkflowSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::WORKFLOW_VIEW)
            && $this->schoolAccess->canAccessWorkflow($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::WORKFLOW_MANAGE)
            && $this->schoolAccess->canAccessWorkflow($user);
    }

    public function decide(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::WORKFLOW_DECIDE)
            && $this->schoolAccess->canAccessWorkflow($user);
    }
}
