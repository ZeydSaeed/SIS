<?php

namespace App\Security\Policies;

use App\Application\Exams\Contracts\GradeEntryScopePort;
use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\GradeSchoolAccessService;
use App\Security\Authorization\Permission;
use App\Security\Context\SchoolContext;

final class GradePolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly GradeSchoolAccessService $schoolAccess,
        private readonly GradeEntryScopePort $entryScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::GRADES_VIEW)
            && $this->schoolAccess->canAccessGrade($user);
    }

    public function view(User $user, StudentGradeRecord|string|int|null $grade = null): bool
    {
        if ($grade === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::GRADES_VIEW)
            && $this->schoolAccess->canAccessGrade($user, $grade);
    }

    public function create(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::GRADES_CREATE)
            && $this->schoolAccess->canAccessGrade($user);
    }

    /**
     * Enter a grade for one exam seat: school-wide with grades.enter.any, otherwise only for a seat the user's own
     * active teaching assignment covers. No other permission implies school-wide entry.
     */
    public function enterSeat(User $user, int $examEnrollmentId): bool
    {
        if (! $this->create($user)) {
            return false;
        }

        if ($this->enterAnySeat($user)) {
            return true;
        }

        return $this->entryScope->teacherCoversSeat((int) $user->id, $this->schoolContext->requireId(), $examEnrollmentId);
    }

    /** School-wide grade entry (grades.enter.any) — entry only; correct / void / finalize stay separately authorized. */
    public function enterAnySeat(User $user): bool
    {
        return $this->create($user)
            && $this->authorization->userHasPermission($user, Permission::GRADES_ENTER_ANY);
    }

    public function correct(User $user, StudentGradeRecord|string|int|null $grade = null): bool
    {
        if ($grade === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::GRADES_CORRECT)
            && $this->schoolAccess->canAccessGrade($user, $grade);
    }

    public function void(User $user, StudentGradeRecord|string|int|null $grade = null): bool
    {
        if ($grade === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::GRADES_VOID)
            && $this->schoolAccess->canAccessGrade($user, $grade);
    }

    public function requestReview(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::GRADES_REVIEW_REQUEST)
            && $this->schoolAccess->canAccessGrade($user);
    }

    public function decideReview(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::GRADES_REVIEW_DECIDE)
            && $this->schoolAccess->canAccessGrade($user);
    }

    public function finalize(User $user, StudentGradeRecord|string|int|null $grade = null): bool
    {
        if ($grade === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::GRADES_FINALIZE)
            && $this->schoolAccess->canAccessGrade($user, $grade);
    }
}
