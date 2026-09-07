<?php

namespace App\Security\Policies;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\StudentSchoolAccessService;

final class StudentPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly StudentSchoolAccessService $schoolAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::STUDENTS_VIEW)
            && $this->schoolAccess->canAccessStudent($user);
    }

    public function view(User $user, StudentRecord|string|int|null $student = null): bool
    {
        if ($student === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::STUDENTS_VIEW)
            && $this->schoolAccess->canAccessStudent($user, $student);
    }

    public function create(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::STUDENTS_CREATE)
            && $this->schoolAccess->canAccessStudent($user);
    }

    public function update(User $user, StudentRecord|string|int|null $student = null): bool
    {
        if ($student === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::STUDENTS_UPDATE)
            && $this->schoolAccess->canAccessStudent($user, $student);
    }

    public function viewPii(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::STUDENTS_VIEW_PII)
            && $this->schoolAccess->canAccessStudent($user);
    }
}
