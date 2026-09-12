<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\DocumentsSchoolAccessService;
use App\Security\Authorization\Permission;

final class DocumentsPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly DocumentsSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::DOCUMENTS_VIEW)
            && $this->schoolAccess->canAccessDocuments($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::DOCUMENTS_MANAGE)
            && $this->schoolAccess->canAccessDocuments($user);
    }
}
