<?php

namespace App\Domain\Teachers\Services;

use App\Domain\Teachers\Contracts\TeachingPlacementCatalogPort;
use App\Domain\Teachers\Data\TeachingAssignmentData;

/**
 * A teaching assignment is valid when every place belongs to the one above it
 * (school → branch → department; school/year → class → section) and the subject is taught
 * there by the curriculum. Returns the first violated rule as an error code.
 */
final class TeachingAssignmentGuard
{
    public function __construct(
        private readonly TeachingPlacementCatalogPort $catalog,
    ) {}

    public function error(TeachingAssignmentData $data): ?string
    {
        if (! $this->catalog->branchInSchool($data->branchId, $data->schoolId)) {
            return 'teachers.assignment_branch_invalid';
        }

        if ($data->departmentId !== null && ! $this->catalog->departmentInBranch($data->departmentId, $data->branchId)) {
            return 'teachers.assignment_department_invalid';
        }

        if ($data->sectionId !== null && $data->classId === null) {
            return 'teachers.assignment_section_needs_class';
        }

        if ($data->classId !== null && ! $this->catalog->classInSchoolYear($data->classId, $data->schoolId, $data->academicYearId)) {
            return 'teachers.assignment_class_invalid';
        }

        if ($data->sectionId !== null && $data->classId !== null && ! $this->catalog->sectionInClass($data->sectionId, $data->classId)) {
            return 'teachers.assignment_section_invalid';
        }

        if (! $this->catalog->subjectInCurriculum($data->schoolId, $data->academicYearId, $data->branchId, $data->departmentId, $data->subjectId)) {
            return 'teachers.assignment_subject_not_in_curriculum';
        }

        return null;
    }
}
