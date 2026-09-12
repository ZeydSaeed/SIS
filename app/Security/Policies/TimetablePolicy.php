<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\TimetableSchoolAccessService;

/**
 * Timetable HTTP authorization boundary.
 * Application remains authoritative for school ownership, lifecycle, and conflicts.
 */
final class TimetablePolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly TimetableSchoolAccessService $schoolAccess,
    ) {}

    public function createSchedule(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TIMETABLE_SCHEDULE_CREATE)
            && $this->schoolAccess->canAccessTimetable($user);
    }

    public function view(User $user, mixed $schedule = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TIMETABLE_VIEW)
            && $this->schoolAccess->canAccessTimetable($user);
    }

    public function updateSchedule(User $user, mixed $schedule = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TIMETABLE_SCHEDULE_UPDATE)
            && $this->schoolAccess->canAccessTimetable($user);
    }

    public function cancelSchedule(User $user, mixed $schedule = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TIMETABLE_SCHEDULE_CANCEL)
            && $this->schoolAccess->canAccessTimetable($user);
    }

    public function createException(User $user, mixed $schedule = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TIMETABLE_EXCEPTION_CREATE)
            && $this->schoolAccess->canAccessTimetable($user);
    }

    public function updateException(User $user, mixed $exception = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::TIMETABLE_EXCEPTION_UPDATE)
            && $this->schoolAccess->canAccessTimetable($user);
    }
}
