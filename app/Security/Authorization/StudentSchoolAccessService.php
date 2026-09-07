<?php

namespace App\Security\Authorization;

use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Models\User;
use App\Security\Context\SchoolContext;

final class StudentSchoolAccessService
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function canAccessStudent(User $user, StudentRecord|string|int|null $student = null): bool
    {
        if (! $this->hasValidSchoolContext($user)) {
            return false;
        }

        $contextSchoolId = $this->schoolContext->requireId();

        if (! in_array($contextSchoolId, $this->schoolScope->allowedSchoolIds($user), true)) {
            return false;
        }

        if ($student === null || $student === StudentRecord::class) {
            return true;
        }

        $record = $this->resolveStudent($student);
        if ($record === null) {
            return false;
        }

        if ($record->school_id === null) {
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

    private function resolveStudent(StudentRecord|string|int $student): ?StudentRecord
    {
        if ($student instanceof StudentRecord) {
            return $student;
        }

        if (is_int($student) || (is_string($student) && ctype_digit($student))) {
            return StudentRecord::query()->find((int) $student);
        }

        return null;
    }
}
