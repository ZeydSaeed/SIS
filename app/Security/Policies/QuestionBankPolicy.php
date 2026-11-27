<?php

namespace App\Security\Policies;

use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\ExamSchoolAccessService;
use App\Security\Authorization\Permission;

/**
 * Question bank HTTP authorization boundary (EXAMIS 7.3a, DEC-050/058). Subject scope, authorship and four eyes are
 * decided again in the Application handlers.
 */
final class QuestionBankPolicy
{
    public function __construct(
        private readonly AuthorizationServiceInterface $authorization,
        private readonly ExamSchoolAccessService $schoolAccess,
    ) {}

    public function viewAny(User $user): bool
    {
        return $this->allowed($user, Permission::BANK_QUESTION_VIEW);
    }

    public function author(User $user): bool
    {
        return $this->allowed($user, Permission::BANK_QUESTION_AUTHOR);
    }

    public function approve(User $user): bool
    {
        return $this->allowed($user, Permission::BANK_QUESTION_APPROVE);
    }

    /** Every subject of the school: approvers. Others are limited to the subjects of their own teaching assignments. */
    public function schoolWide(User $user): bool
    {
        return $this->approve($user);
    }

    /** The answer key is shown to people who write or decide questions, never to view-only users. */
    public function viewAnswerKey(User $user): bool
    {
        return $this->author($user) || $this->approve($user);
    }

    private function allowed(User $user, string $permission): bool
    {
        return $this->authorization->userHasPermission($user, $permission)
            && $this->schoolAccess->canAccessExam($user);
    }
}
