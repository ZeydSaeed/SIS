<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\PortalPartyAccessService;

/**
 * Portal official-results HTTP authorization (ownership enforced in controller).
 */
final class PortalResultsPolicy
{
    public function __construct(
        private readonly PortalPartyAccessService $portalAccess,
    ) {}

    public function viewPortal(User $user): bool
    {
        return $this->portalAccess->canViewPortal($user);
    }
}
