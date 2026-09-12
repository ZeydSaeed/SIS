<?php

namespace App\Domain\Vocational\Repositories;

use App\Domain\Vocational\Data\SpecializationRead;

interface VocationalCatalogRepositoryInterface
{
    public function createSpecialization(
        int $schoolId,
        string $code,
        string $name,
        ?string $description,
        string $at,
    ): int;

    public function updateSpecialization(
        int $schoolId,
        int $specializationId,
        string $code,
        string $name,
        ?string $description,
        string $at,
    ): void;

    public function deactivateSpecialization(int $schoolId, int $specializationId, string $at): void;

    public function createTrack(
        int $schoolId,
        int $specializationId,
        string $code,
        string $name,
        string $at,
    ): int;

    public function updateTrack(
        int $schoolId,
        int $trackId,
        string $code,
        string $name,
        string $at,
    ): void;

    public function deactivateTrack(int $schoolId, int $trackId, string $at): void;

    public function linkSpecializationSubject(
        int $schoolId,
        int $specializationId,
        int $subjectId,
        bool $isRequired,
        ?int $creditHours,
    ): int;

    public function deactivateSpecializationSubject(int $schoolId, int $linkId): void;

    /**
     * @return array{items: list<SpecializationRead>, total: int}
     */
    public function listSpecializations(
        int $schoolId,
        ?int $status,
        int $page,
        int $perPage,
    ): array;

    public function findSpecialization(int $schoolId, int $specializationId, bool $withChildren = true): ?SpecializationRead;
}
