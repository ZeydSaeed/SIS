<?php

namespace App\Security\Policies;

use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\EnrollmentSchoolAccessService;
use App\Security\Authorization\Permission;

final class EnrollmentPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly EnrollmentSchoolAccessService $schoolAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ENROLLMENT_VIEW)
            && $this->schoolAccess->canAccessEnrollment($user);
    }

    public function view(User $user, EnrollmentRecord|string|int|null $enrollment = null): bool
    {
        if ($enrollment === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::ENROLLMENT_VIEW)
            && $this->schoolAccess->canAccessEnrollment($user, $enrollment);
    }

    public function create(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ENROLLMENT_CREATE)
            && $this->schoolAccess->canAccessEnrollment($user);
    }
}
