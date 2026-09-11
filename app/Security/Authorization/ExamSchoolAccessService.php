<?php

namespace App\Security\Authorization;

use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Models\User;
use App\Security\Context\SchoolContext;

final class ExamSchoolAccessService
{
    public function __construct(
        private readonly SchoolScopeService $schoolScope,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function canAccessExam(User $user, ExamRecord|string|int|null $exam = null): bool
    {
        if (! $this->hasValidSchoolContext($user)) {
            return false;
        }

        $contextSchoolId = $this->schoolContext->requireId();

        if (! in_array($contextSchoolId, $this->schoolScope->allowedSchoolIds($user), true)) {
            return false;
        }

        if ($exam === null || $exam === ExamRecord::class) {
            return true;
        }

        $record = $this->resolveExam($exam);
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

    private function resolveExam(ExamRecord|string|int $exam): ?ExamRecord
    {
        if ($exam instanceof ExamRecord) {
            return $exam;
        }

        if (is_int($exam) || (is_string($exam) && ctype_digit($exam))) {
            return ExamRecord::query()->find((int) $exam);
        }

        return null;
    }
}
