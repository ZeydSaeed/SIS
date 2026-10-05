<?php

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Data\DirectorateSnapshot;

interface DirectorateRepositoryInterface
{
    public function find(int $directorateId): ?DirectorateSnapshot;

    /** Ministry new directorates belong to (the single active ministry), or null when none exists. */
    public function defaultMinistryId(): ?int;

    /** Next free system code (codes are generated, never typed by users). */
    public function nextCode(): string;

    public function create(int $ministryId, string $code, string $name, ?string $region, string $createdAt): int;

    /**
     * @param  array{name?: string, region?: ?string}  $fields
     */
    public function update(int $directorateId, array $fields, string $updatedAt): bool;

    public function setStatus(int $directorateId, int $status, string $updatedAt): bool;

    /** Active schools (any tenant) that still reference the directorate. */
    public function activeSchoolCount(int $directorateId): int;

    /**
     * Of the given schools, those currently in the directorate.
     *
     * @param  list<int>  $schoolIds
     * @return list<int>
     */
    public function schoolIdsIn(int $directorateId, array $schoolIds): array;
}
