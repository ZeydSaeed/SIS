<?php

namespace App\Domain\Curriculum\Repositories;

use App\Domain\Curriculum\Data\PrerequisiteSnapshot;

interface PrerequisiteRepositoryInterface
{
    public function subjectExists(int $subjectId): bool;

    public function addOrReactivate(int $subjectId, int $prerequisiteSubjectId, string $createdAt): int;

    public function deactivate(int $prerequisiteId): bool;

    public function reactivate(int $prerequisiteId): bool;

    public function findActive(int $prerequisiteId): ?PrerequisiteSnapshot;

    public function findInactive(int $prerequisiteId): ?PrerequisiteSnapshot;

    /** @return list<PrerequisiteSnapshot> */
    public function listActiveForSubject(int $subjectId): array;

    /** @return list<int> active prerequisite_subject_id edges from $subjectId */
    public function activePrerequisiteSubjectIds(int $subjectId): array;
}
