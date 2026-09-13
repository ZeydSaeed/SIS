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
        string $createdAt,
    ): int;

    public function findActiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot;

    /** @return list<CurriculumSnapshot> */
    public function listActiveForSchool(int $schoolId, int $academicYearId): array;

    public function deactivate(int $schoolId, int $curriculumId): bool;

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

    /** @return list<CurriculumSubjectSnapshot> */
    public function listActiveLinks(int $schoolId, int $curriculumId): array;

    public function deactivateLink(int $schoolId, int $linkId): bool;
}
