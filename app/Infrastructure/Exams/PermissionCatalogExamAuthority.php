<?php

namespace App\Infrastructure\Exams;

use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Domain\Exams\Exceptions\ExamAuthorityDeniedException;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;
use App\Models\User;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\SchoolScopeService;
use App\Security\Context\SchoolContext;

/**
 * HD-001 — grades_manager owns exam.create / exam.update / exam.cancel.
 * Phase 7.2 — grades_manager owns exam.session.* / exam.enrollment.* (locked catalog).
 */
final class PermissionCatalogExamAuthority implements ExamAdministrationAuthorityPort
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly AuthorizationServiceInterface $authorization,
        private readonly SchoolScopeService $schoolScope,
    ) {}

    public function assertSchoolMatches(int $commandSchoolId): void
    {
        $ctx = $this->schoolContext->id();
        if ($ctx === null) {
            throw ExamAuthorityDeniedException::missingSchoolContext();
        }

        if ($ctx !== $commandSchoolId) {
            throw ExamAuthorityDeniedException::schoolMismatch();
        }
    }

    public function assertCan(ExamAdministrationAction $action, int $actorUserId, int $schoolId): void
    {
        $this->assertSchoolMatches($schoolId);

        $user = User::query()->find($actorUserId);
        if ($user === null) {
            throw ExamAuthorityDeniedException::forAction($action->value);
        }

        if (! in_array($schoolId, $this->schoolScope->allowedSchoolIds($user), true)) {
            throw ExamAuthorityDeniedException::schoolMismatch();
        }

        $permission = match ($action) {
            ExamAdministrationAction::Create => Permission::EXAM_CREATE,
            ExamAdministrationAction::Update => Permission::EXAM_UPDATE,
            ExamAdministrationAction::Cancel => Permission::EXAM_CANCEL,
            ExamAdministrationAction::CreateSession => Permission::EXAM_SESSION_CREATE,
            ExamAdministrationAction::UpdateSession => Permission::EXAM_SESSION_UPDATE,
            ExamAdministrationAction::OpenSession => Permission::EXAM_SESSION_OPEN,
            ExamAdministrationAction::CloseSession => Permission::EXAM_SESSION_CLOSE,
            ExamAdministrationAction::CreateEnrollment => Permission::EXAM_ENROLLMENT_CREATE,
            ExamAdministrationAction::UpdateEnrollment => Permission::EXAM_ENROLLMENT_UPDATE,
            ExamAdministrationAction::CancelEnrollment => Permission::EXAM_ENROLLMENT_CANCEL,
            ExamAdministrationAction::PresentEnrollment => Permission::EXAM_ENROLLMENT_PRESENT,
        };

        if (! $this->authorization->userHasPermission($user, $permission)) {
            throw ExamAuthorityDeniedException::forAction($action->value);
        }
    }
}
