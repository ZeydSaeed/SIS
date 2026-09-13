<?php

namespace App\Domain\Curriculum\Repositories;

use App\Domain\Curriculum\Data\CurriculumSnapshot;
use App\Domain\Curriculum\Data\CurriculumSubjectSnapshot;

interface CurriculumRepositoryInterface
{
    public function gradeLevelExists(int $gradeLevelId): bool;

    public function academicYearExists(int $academicYearId): bool;

    public function create(
        int $schoolId,
        int $academicYearId,
        int $gradeLevelId,
        string $name,
        ?int $specializationId,
        string $createdAt,
    ): int;

    public function findActiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot;

    public function findInactiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot;

    /** @return list<CurriculumSnapshot> */
    public function listActiveForSchool(int $schoolId, int $academicYearId): array;

    public function deactivate(int $schoolId, int $curriculumId): bool;

    public function reactivate(int $schoolId, int $curriculumId): bool;

    public function updateSpecialization(
        int $schoolId,
        int $curriculumId,
        ?int $specializationId,
        string $updatedAt,
    ): bool;

    /**
     * @param  array{name?: string, specialization_id?: ?int}  $fields
     */
    public function updateActive(
        int $schoolId,
        int $curriculumId,
        array $fields,
        string $updatedAt,
    ): bool;

    public function linkSubject(
        int $schoolId,
        int $curriculumId,
        int $subjectId,
        ?int $weeklyHours,
        bool $isRequired,
        int $subjectOrder,
        string $createdAt,
    ): int;

    public function findActiveLink(int $schoolId, int $linkId): ?CurriculumSubjectSnapshot;

    public function findInactiveLink(int $schoolId, int $linkId): ?CurriculumSubjectSnapshot;

    /** @return list<CurriculumSubjectSnapshot> */
    public function listActiveLinks(int $schoolId, int $curriculumId): array;

    public function deactivateLink(int $schoolId, int $linkId): bool;

    public function reactivateLink(int $schoolId, int $linkId): bool;
}
