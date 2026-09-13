<?php

namespace App\Domain\Enrollment\Contracts;

/**
 * Read-only grade-pass evidence for prerequisite satisfaction (CUR-U06).
 */
interface PrerequisitePassEvidencePort
{
    public function studentHasPassingGrade(int $schoolId, int $studentId, int $subjectId): bool;
}
