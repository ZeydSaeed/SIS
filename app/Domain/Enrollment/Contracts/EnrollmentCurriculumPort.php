<?php

namespace App\Domain\Enrollment\Contracts;

/**
 * Read-only view of the curriculum that governs an enrollment placement.
 *
 * A curriculum governs an enrollment when it is active and shares the school,
 * academic year and class grade level; a curriculum bound to a الاختصاص
 * (department) only governs enrollments of that department.
 */
interface EnrollmentCurriculumPort
{
    public function subjectInGoverningCurriculum(
        int $schoolId,
        int $academicYearId,
        int $classId,
        ?int $departmentId,
        int $subjectId,
    ): bool;

    /** @return list<int> Required, active subjects of every curriculum governing the placement. */
    public function requiredSubjectIdsForPlacement(
        int $schoolId,
        int $academicYearId,
        int $classId,
        ?int $departmentId,
    ): array;

    /** @return list<int> Active enrollments governed by the given (active) curriculum. */
    public function enrollmentIdsGovernedBy(int $schoolId, int $curriculumId): array;
}
