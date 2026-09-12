<?php

namespace App\Application\Portal\Support;

use App\Application\Portal\Commands\LinkPortalPartyScopeCommand;
use App\Domain\Portal\Repositories\PortalScopeRepositoryInterface;
use App\Domain\Portal\ValueObjects\PortalScopeType;

final class PortalScopeLinkRules
{
    public function __construct(
        private readonly PortalScopeRepositoryInterface $scopes,
    ) {}

    public function validate(LinkPortalPartyScopeCommand $command): ?string
    {
        if (! in_array($command->scopeType, [PortalScopeType::STUDENT, PortalScopeType::GUARDIAN], true)) {
            return 'portal.scopes.invalid_type';
        }

        if (! $this->scopes->userExists($command->userId)) {
            return 'portal.scopes.user_not_found';
        }

        return $command->scopeType === PortalScopeType::STUDENT
            ? $this->validateStudent($command->scopeId, $command->schoolId)
            : $this->validateGuardian($command->scopeId, $command->schoolId);
    }

    private function validateStudent(int $studentId, int $schoolId): ?string
    {
        if (! $this->scopes->studentExists($studentId)) {
            return 'portal.scopes.student_not_found';
        }

        if (! $this->scopes->studentHasEnrollmentInSchool($studentId, $schoolId)) {
            return 'portal.scopes.student_not_in_school';
        }

        return null;
    }

    private function validateGuardian(int $guardianId, int $schoolId): ?string
    {
        if (! $this->scopes->guardianExists($guardianId)) {
            return 'portal.scopes.guardian_not_found';
        }

        if (! $this->scopes->guardianLinkedToStudentInSchool($guardianId, $schoolId)) {
            return 'portal.scopes.guardian_not_linked_in_school';
        }

        return null;
    }
}
