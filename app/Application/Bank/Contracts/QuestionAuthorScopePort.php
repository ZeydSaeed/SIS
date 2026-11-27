<?php

namespace App\Application\Bank\Contracts;

/**
 * Who may author questions of which subject (DEC-050/055). A teacher authors questions of the subjects of their active
 * teaching assignments in the academic year; school-wide authoring (the approve permission) is decided by the caller,
 * never here.
 */
interface QuestionAuthorScopePort
{
    /** @return list<int> subjects of the user's active teaching assignments in the school that year */
    public function subjectIdsForUser(int $userId, int $schoolId, int $academicYearId): array;

    public function isActiveSubject(int $subjectId): bool;
}
