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

    /**
     * Seats of one session the user's assignments cover (same rule as teacherCoversSeat).
     *
     * @return list<int>
     */
    public function coveredSeatIds(int $userId, int $schoolId, int $examSessionId): array;

    /**
     * Sessions of the academic year with at least one seat the user's assignments cover.
     *
     * @return list<int>
     */
    public function coveredSessionIds(int $userId, int $schoolId, int $academicYearId): array;
}
