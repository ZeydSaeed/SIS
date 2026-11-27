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
        ?int $departmentId = null,
        ?int $branchId = null,
        ?int $classId = null,
        ?int $sectionId = null,
    ): int;

    public function departmentActiveInSchool(int $schoolId, int $departmentId): bool;

    public function branchActiveInSchool(int $schoolId, int $branchId): bool;

    public function departmentActiveInBranch(int $departmentId, int $branchId): bool;

    /** The grade level of an active class of the school and year, or null when there is no such class. */
    public function activeClassGradeLevel(int $schoolId, int $academicYearId, int $classId): ?int;

    public function sectionActiveInClass(int $sectionId, int $classId): bool;

    /** An active curriculum already covers this branch / department / section in the year (optionally ignoring one). */
    public function activeScopeExists(
        int $schoolId,
        int $academicYearId,
        int $branchId,
        ?int $departmentId,
        int $sectionId,
        ?int $exceptCurriculumId = null,
    ): bool;

    /** Any teacher (active or ended) is recorded on one of the curriculum's subject lines. */
    public function hasLineTeachers(int $schoolId, int $curriculumId): bool;

    /**
     * Per curriculum: active subject lines, their weekly hours, and how many of them have an active main teacher
     * (one query for the whole page).
     *
     * @param  list<int>  $curriculumIds
     * @return array<int, array{subjects: int, hours: int, staffed: int}>
     */
    public function lineStats(int $schoolId, array $curriculumIds): array;

    /** An active curriculum of the department for the grade and year already exists (optionally ignoring one). */
    public function activeCurriculumExists(int $schoolId, int $academicYearId, int $gradeLevelId, int $departmentId, ?int $exceptCurriculumId = null): bool;

    public function findActiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot;

    public function findInactiveInSchool(int $schoolId, int $curriculumId): ?CurriculumSnapshot;

    /** @return list<CurriculumSnapshot> */
    public function listActiveForSchool(int $schoolId, int $academicYearId): array;

    /** @return list<CurriculumSnapshot> */
    public function listForSchool(int $schoolId, int $academicYearId): array;

    /**
     * Filtered/paginated curricula for operational UI.
     *
     * @return array{items: list<CurriculumSnapshot>, total: int}
     */
    public function searchForSchool(
        int $schoolId,
        ?int $academicYearId,
        ?int $status,
        int|array|null $gradeLevelId,
        int|array|null $specializationId,
        int|array|null $branchId,
        string $q,
        int $page,
        int $perPage,
        int|array|null $departmentId = null,
    ): array;

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
        ?int $theoryHours = null,
        ?int $practicalHours = null,
    ): int;

    /** Set «نظري» / «عملي» hours of an active link; weekly_hours becomes their sum. */
    public function updateLinkHours(int $schoolId, int $linkId, ?int $theoryHours, ?int $practicalHours): bool;

    public function findActiveLink(int $schoolId, int $linkId): ?CurriculumSubjectSnapshot;

    public function findInactiveLink(int $schoolId, int $linkId): ?CurriculumSubjectSnapshot;

    /** @return list<CurriculumSubjectSnapshot> */
    public function listActiveLinks(int $schoolId, int $curriculumId): array;

    /** @return list<CurriculumSubjectSnapshot> */
    public function listLinks(int $schoolId, int $curriculumId): array;

    /**
     * The school's curricula each subject is linked to (any line status) — the «حذف» warning names them.
     *
     * @return array<int, list<string>> subject id → curriculum names
     */
    public function curriculumNamesBySubject(int $schoolId): array;

    public function deactivateLink(int $schoolId, int $linkId): bool;

    public function reactivateLink(int $schoolId, int $linkId): bool;
}
