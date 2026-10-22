<?php

namespace App\Application\Exams\Contracts;

/**
 * Teacher scope for grade entry: which exam seats a user may grade through their own teaching assignments.
 * School-wide entry (grades.enter.any) is decided by the caller, never here.
 */
interface GradeEntryScopePort
{
    /** True only when the user is linked to an active teacher whose active assignment covers the seat. */
    public function teacherCoversSeat(int $userId, int $schoolId, int $examEnrollmentId): bool;
}
