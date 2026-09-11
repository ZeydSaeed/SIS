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

    public function create(User $user): bool
    {
        return $this->authorization->userHasPermission($user, Permission::EXAM_CREATE)
            && $this->schoolAccess->canAccessExam($user);
    }

    public function update(User $user, ExamRecord|string|int|null $exam = null): bool
    {
        if ($exam === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::EXAM_UPDATE)
            && $this->schoolAccess->canAccessExam($user, $exam);
    }

    public function cancel(User $user, ExamRecord|string|int|null $exam = null): bool
    {
        if ($exam === null) {
            return false;
        }

        return $this->authorization->userHasPermission($user, Permission::EXAM_CANCEL)
            && $this->schoolAccess->canAccessExam($user, $exam);
    }
}
