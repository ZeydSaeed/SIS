<?php

namespace App\Domain\Teachers\Contracts;

/**
 * Read-only view of Organization / Enrollment / Curriculum for a teaching assignment:
 * where (branch → department, class → section) and what (subject in the curriculum).
 */
interface TeachingPlacementCatalogPort
{
    /** Active branch of the school. */
    public function branchInSchool(int $branchId, int $schoolId): bool;

    /** Active department of the branch. */
    public function departmentInBranch(int $departmentId, int $branchId): bool;

    /** Active class of the school in the academic year. */
    public function classInSchoolYear(int $classId, int $schoolId, int $academicYearId): bool;

    /** Active section of the class. */
    public function sectionInClass(int $sectionId, int $classId): bool;

    /**
     * The subject is in an active curriculum of the school/year for the department
     * (or, without a department, for any department of the branch / a general curriculum).
     */
    public function subjectInCurriculum(int $schoolId, int $academicYearId, int $branchId, ?int $departmentId, int $subjectId): bool;
}
