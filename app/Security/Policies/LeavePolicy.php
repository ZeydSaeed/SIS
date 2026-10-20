<?php

namespace App\Security\Policies;

use App\Domain\Leave\Repositories\LeaveDelegationRepositoryInterface;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;

/**
 * «الإجازات والغياب» HTTP authorization: a permission of the catalogue AND the current school among the user's
 * schools. Row-level rules (own request, self-approval, sensitive masking) are enforced again by the Application
 * layer; RLS bounds every table to the school.
 */
final class LeavePolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
        private readonly LeaveDelegationRepositoryInterface $delegations,
    ) {}

    /** Opens the module: sees the school's leave, or at least files their own. */
    public function access(User $user): bool
    {
        return $this->view($user) || $this->request($user) || $this->manageRequests($user);
    }

    public function view(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_VIEW) || $this->delegated($user);
    }

    public function request(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_REQUEST_CREATE);
    }

    public function manageRequests(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_REQUEST_MANAGE);
    }

    /** Decides leave: holds the permission, or stands in today for an approver (see {@see delegated()}). */
    public function approve(User $user): bool
    {
        return $this->holdsApprove($user) || $this->delegated($user);
    }

    /** The permission itself — what a delegator must hold to delegate it (a delegate cannot pass it on). */
    public function holdsApprove(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_REQUEST_APPROVE);
    }

    public function cancel(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_REQUEST_CANCEL);
    }

    public function managePolicies(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_POLICY_MANAGE);
    }

    public function viewBalances(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_BALANCE_VIEW);
    }

    public function adjustBalances(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_BALANCE_ADJUST);
    }

    public function viewSensitive(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_SENSITIVE_VIEW);
    }

    public function viewReports(User $user): bool
    {
        return $this->allows($user, Permission::LEAVE_REPORTS_VIEW);
    }

    /**
     * A delegate decides only while an active delegation lasts AND its delegator still holds the approval permission in
     * this school — a delegation never outlives, nor exceeds, the delegator's own authority.
     */
    private function delegated(User $user): bool
    {
        $schoolId = $this->schoolContext->id();
        if ($schoolId === null || ! in_array($schoolId, $this->schoolScope->allowedSchoolIds($user), true)) {
            return false;
        }
        foreach ($this->delegations->activeDelegatorIds($schoolId, (int) $user->id) as $delegatorId) {
            $delegator = User::query()->find($delegatorId);
            if ($delegator instanceof User && $this->holdsApprove($delegator)) {
                return true;
            }
        }

        return false;
    }

    private function allows(User $user, string $permission): bool
    {
        if (! $this->authorization->userHasPermission($user, $permission)) {
            return false;
        }
        $schoolId = $this->schoolContext->id();

        return $schoolId !== null && in_array($schoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }
}
