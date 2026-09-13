<?php

namespace App\Domain\Curriculum\Repositories;

use App\Domain\Curriculum\Data\SubjectSnapshot;

interface SubjectRepositoryInterface
{
    public function codeExists(string $code): bool;

    public function create(
        string $code,
        string $name,
        ?string $nameEn,
        int $subjectType,
        ?int $creditHours,
        int $maxGrade,
        int $passGrade,
        string $createdAt,
    ): int;

    public function findActive(int $subjectId): ?SubjectSnapshot;

    /** @return list<SubjectSnapshot> */
    public function listActive(): array;

    public function deactivate(int $subjectId): bool;
}
