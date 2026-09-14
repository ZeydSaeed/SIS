<?php

namespace App\Domain\Vocational\Repositories;

use App\Domain\Vocational\Data\SpecializationRead;
use App\Domain\Vocational\Data\SpecializationSubjectSnapshot;
use App\Domain\Vocational\Data\TrackSnapshot;

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

    public function reactivateSpecialization(int $schoolId, int $specializationId, string $at): void;

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

    public function reactivateTrack(int $schoolId, int $trackId, string $at): void;

    public function linkSpecializationSubject(
        int $schoolId,
        int $specializationId,
        int $subjectId,
        bool $isRequired,
        ?int $creditHours,
    ): int;

    public function deactivateSpecializationSubject(int $schoolId, int $linkId): void;

    public function reactivateSpecializationSubject(int $schoolId, int $linkId): void;

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

    public function findTrack(int $schoolId, int $trackId): ?TrackSnapshot;

    public function findSpecializationSubjectLink(int $schoolId, int $linkId): ?SpecializationSubjectSnapshot;

    /**
     * @return list<TrackSnapshot>
     */
    public function listTracksForSpecialization(int $schoolId, int $specializationId): array;

    public function specializationBelongsToSchool(int $schoolId, int $specializationId): bool;

    /** @return list<SpecializationSubjectSnapshot>|null null when specialization missing */
    public function listSpecializationSubjects(int $schoolId, int $specializationId, ?int $status): ?array;
}
