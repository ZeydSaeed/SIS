<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\PromotionSchoolAccessService;

final class PromotionPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly PromotionSchoolAccessService $schoolAccess,
    ) {}

    public function view(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::PROMOTION_VIEW)
            && $this->schoolAccess->canAccessPromotion($user);
    }

    public function manage(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::PROMOTION_MANAGE)
            && $this->schoolAccess->canAccessPromotion($user);
    }
}
