<?php

namespace App\Security\Authorization;

use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Models\User;
use App\Security\Context\SchoolContext;

final class GradeSchoolAccessService
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function canAccessGrade(User $user, StudentGradeRecord|string|int|null $grade = null): bool
    {
        if (! $this->hasValidSchoolContext($user)) {
            return false;
        }

        $contextSchoolId = $this->schoolContext->requireId();

        if (! in_array($contextSchoolId, $this->schoolScope->allowedSchoolIds($user), true)) {
            return false;
        }

        if ($grade === null || $grade === StudentGradeRecord::class) {
            return true;
        }

        $record = $this->resolveGrade($grade);
        if ($record === null) {
            return false;
        }

        return (int) $record->school_id === $contextSchoolId;
    }

    private function hasValidSchoolContext(User $user): bool
    {
        try {
            $schoolId = $this->schoolContext->requireId();
        } catch (\Throwable) {
            return false;
        }

        return in_array($schoolId, $this->schoolScope->allowedSchoolIds($user), true);
    }

    private function resolveGrade(StudentGradeRecord|string|int $grade): ?StudentGradeRecord
    {
        if ($grade instanceof StudentGradeRecord) {
            return $grade;
        }

        if (is_int($grade) || (is_string($grade) && ctype_digit($grade))) {
            return StudentGradeRecord::query()->find((int) $grade);
        }

        return null;
    }
}
