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

    /** Open / save / submit sheets of own lessons (lesson ownership is enforced by the Application layer). */
    public function submitSheet(User $user, mixed $session = null): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_SHEET_SUBMIT) || $this->reviewSheet($user);
    }

    public function reviewSheet(User $user, mixed $session = null): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_SHEET_REVIEW);
    }

    public function submitExcuse(User $user): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_EXCUSE_SUBMIT);
    }

    public function reviewExcuse(User $user): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_EXCUSE_REVIEW);
    }

    public function viewExcuses(User $user): bool
    {
        return $this->submitExcuse($user) || $this->reviewExcuse($user);
    }

    public function recordStaff(User $user): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_STAFF_RECORD);
    }

    public function staffCheckIn(User $user): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_STAFF_CHECKIN);
    }

    public function viewReports(User $user): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_REPORTS_VIEW);
    }

    public function manageAlerts(User $user): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_ALERTS_MANAGE);
    }

    public function managePolicy(User $user): bool
    {
        return $this->allows($user, Permission::ATTENDANCE_POLICY_MANAGE);
    }

    /** Linking a teacher record to a sign-in account is teacher administration. */
    public function linkTeacherAccount(User $user): bool
    {
        return $this->allows($user, Permission::TEACHERS_MANAGE);
    }

    private function allows(User $user, string $permission): bool
    {
        return $this->authorization->userHasPermission($user, $permission)
            && $this->schoolAccess->canAccessAttendance($user);
    }
}
