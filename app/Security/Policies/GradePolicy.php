<?php

namespace App\Security\Policies;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\GradeSchoolAccessService;
use App\Security\Authorization\Permission;

final class GradePolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly GradeSchoolAccessService $schoolAccess,
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

    public function finalize(User $user, StudentGradeRecord|string|int|null $grade = null): bool
    {
        if ($grade === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::GRADES_FINALIZE)
            && $this->schoolAccess->canAccessGrade($user, $grade);
    }
}
