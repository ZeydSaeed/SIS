<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\ResultsSchoolAccessService;

/**
 * Results HTTP authorization boundary (official reads + staff writers).
 * Application remains authoritative for lifecycle, official-current, and tenant filters.
 */
final class ResultsPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly ResultsSchoolAccessService $schoolAccess,
    ) {}

    public function viewOfficial(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::RESULTS_VIEW)
            && $this->schoolAccess->canAccessResults($user);
    }

    public function calculate(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::RESULTS_CALCULATE)
            && $this->schoolAccess->canAccessResults($user);
    }

    public function finalize(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::RESULTS_FINALIZE)
            && $this->schoolAccess->canAccessResults($user);
    }

    public function buildRanking(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::RESULTS_RANKING_BUILD)
            && $this->schoolAccess->canAccessResults($user);
    }

    public function issueTranscript(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::RESULTS_TRANSCRIPT_ISSUE)
            && $this->schoolAccess->canAccessResults($user);
    }

    public function rebuild(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::RESULTS_REBUILD)
            && $this->schoolAccess->canAccessResults($user);
    }
}
