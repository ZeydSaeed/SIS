<?php

namespace App\Security\Policies;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\ExamSchoolAccessService;
use App\Security\Authorization\Permission;

final class ExamPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly ExamSchoolAccessService $schoolAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_VIEW)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function view(User $user, ExamRecord|string|int|null $exam = null): bool
    {
        // Class-level / missing model: same as viewAny (avoid Eloquent resolve under RLS).
        if ($exam === null || $exam === ExamRecord::class || is_string($exam) && ! is_numeric($exam)) {
            return $this->viewAny($user);
        }

        return $this->authorization->userHasPermission($user, Permission::EXAM_VIEW)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function create(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_CREATE)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function update(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_UPDATE)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function cancel(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_CANCEL)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function createSession(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_SESSION_CREATE)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function updateSession(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_SESSION_UPDATE)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function openSession(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_SESSION_OPEN)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function closeSession(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_SESSION_CLOSE)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function createEnrollment(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_ENROLLMENT_CREATE)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function updateEnrollment(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_ENROLLMENT_UPDATE)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function cancelEnrollment(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_ENROLLMENT_CANCEL)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function presentEnrollment(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_ENROLLMENT_PRESENT)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function reopenEnrollment(User $user, mixed $exam = null): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_ENROLLMENT_UPDATE)
            && $this->schoolAccess->canAccessExam($user);
    }
}
