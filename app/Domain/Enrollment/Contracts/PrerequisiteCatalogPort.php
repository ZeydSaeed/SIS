<?php

namespace App\Domain\Enrollment\Contracts;

/**
 * Read-only curriculum catalog views needed by enrollment subject assignment.
 */
interface PrerequisiteCatalogPort
{
    public function subjectIsActive(int $subjectId): bool;

    /** @return list<int> */
    public function activePrerequisiteSubjectIds(int $subjectId): array;
}
