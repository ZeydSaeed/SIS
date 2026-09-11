<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\AttendanceSchoolAccessService;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;

/**
 * Attendance HTTP authorization boundary (R1.3 + R1.5 Gate registration).
 * Application remains authoritative for school ownership, lifecycle, and ATT-D4.
 */
final class AttendancePolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly AttendanceSchoolAccessService $schoolAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ATTENDANCE_VIEW)
            && $this->schoolAccess->canAccessAttendance($user);
    }

    public function view(User $user, mixed $session = null): bool
    {
        return $this->viewAny($user);
    }

    public function createSession(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ATTENDANCE_SESSION_CREATE)
            && $this->schoolAccess->canAccessAttendance($user);
    }

    public function mark(User $user, mixed $session = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ATTENDANCE_MARK)
            && $this->schoolAccess->canAccessAttendance($user);
    }

    public function correct(User $user, mixed $session = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ATTENDANCE_CORRECT)
            && $this->schoolAccess->canAccessAttendance($user);
    }

    public function closeSession(User $user, mixed $session = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ATTENDANCE_SESSION_CLOSE)
            && $this->schoolAccess->canAccessAttendance($user);
    }

    public function cancelSession(User $user, mixed $session = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::ATTENDANCE_SESSION_CANCEL)
            && $this->schoolAccess->canAccessAttendance($user);
    }
}
