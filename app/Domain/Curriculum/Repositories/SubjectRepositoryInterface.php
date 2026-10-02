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
        ?string $prerequisitesText = null,
    ): int;

    public function findActive(int $subjectId): ?SubjectSnapshot;

    public function findInactive(int $subjectId): ?SubjectSnapshot;

    /** @return list<SubjectSnapshot> */
    public function listActive(): array;

    /** @return list<SubjectSnapshot> */
    public function listAll(): array;

    /**
     * @return array{items: list<SubjectSnapshot>, total: int}
     */
    public function search(
        ?int $status,
        ?int $subjectType,
        string $q,
        int $page,
        int $perPage,
    ): array;

    public function deactivate(int $subjectId): bool;

    public function reactivate(int $subjectId): bool;

    /**
     * @param  array{
     *     name?: string,
     *     name_en?: ?string,
     *     subject_type?: int,
     *     credit_hours?: ?int,
     *     max_grade?: int,
     *     pass_grade?: int,
     *     prerequisites_text?: ?string
     * }  $fields
     */
    public function updateActive(int $subjectId, array $fields, string $updatedAt): bool;
}
