<?php

namespace App\Application\Bank\Support;

use App\Application\Bank\Contracts\QuestionAuthorScopePort;
use App\Domain\Bank\Exceptions\QuestionBankException;

/** Server-side subject scope of authoring: school-wide authors (approve permission) or the teacher's own subjects. */
final class QuestionAuthorAccess
{
    public function __construct(
        private readonly QuestionAuthorScopePort $scope,
    ) {}

    public function assertMayAuthor(int $subjectId, int $actorId, int $schoolId, int $academicYearId, bool $schoolWide): void
    {
        if (! $this->scope->isActiveSubject($subjectId)) {
            throw QuestionBankException::subjectNotAllowed();
        }
        if ($schoolWide) {
            return;
        }
        if (! in_array($subjectId, $this->scope->subjectIdsForUser($actorId, $schoolId, $academicYearId), true)) {
            throw QuestionBankException::subjectNotAllowed();
        }
    }
}
