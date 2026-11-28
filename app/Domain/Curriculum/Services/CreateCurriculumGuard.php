<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Contracts\SpecializationCatalogPort;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class CreateCurriculumGuard
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly SpecializationCatalogPort $specializations,
    ) {}

    /**
     * The «المناهج» page sends the full place — branch → department, class → section — and each part must belong to
     * the one above it (school / year / grade level). API callers may still send only the grade level (+ department).
     */
    public function rejectionCode(
        int $schoolId,
        int $academicYearId,
        int $gradeLevelId,
        string $name,
        ?int $specializationId,
        ?int $departmentId = null,
        ?int $branchId = null,
        ?int $classId = null,
        ?int $sectionId = null,
        ?int $plannedSubjectCount = null,
        /** When editing: the curriculum itself does not count as a duplicate. */
        ?int $exceptCurriculumId = null,
        /** The subjects it will have: never more than «عدد المواد» (raise the count first). */
        ?int $subjectCount = null,
    ): ?string {
        if (trim($name) === '') {
            return 'curriculum.curriculum_name_invalid';
        }
        if ($plannedSubjectCount !== null && ($plannedSubjectCount < 1 || $plannedSubjectCount > 200)) {
            return 'curriculum.planned_subject_count_invalid';
        }
        if ($plannedSubjectCount !== null && $subjectCount !== null && $subjectCount > $plannedSubjectCount) {
            return 'curriculum.subjects_exceed_planned';
        }
        if ($academicYearId < 1 || ! $this->curricula->academicYearExists($academicYearId)) {
            return 'curriculum.academic_year_invalid';
        }
        if ($gradeLevelId < 1 || ! $this->curricula->gradeLevelExists($gradeLevelId)) {
            return 'curriculum.grade_level_invalid';
        }
        if ($departmentId !== null
            && ($departmentId < 1 || ! $this->curricula->departmentActiveInSchool($schoolId, $departmentId))) {
            return 'curriculum.department_invalid';
        }
        $placeError = $this->placeError($schoolId, $academicYearId, $gradeLevelId, $departmentId, $branchId, $classId, $sectionId);
        if ($placeError !== null) {
            return $placeError;
        }
        if ($sectionId !== null && $branchId !== null) {
            // A curriculum per branch / department / section: sections A and B of one class may differ.
            if ($this->curricula->activeScopeExists($schoolId, $academicYearId, $branchId, $departmentId, $sectionId, $exceptCurriculumId)) {
                return 'curriculum.curriculum_exists';
            }
        } elseif ($classId !== null && $branchId !== null) {
            // A class-level curriculum (each teacher names his section): one per branch / department / class.
            if ($this->curricula->activeClassScopeExists($schoolId, $academicYearId, $branchId, $departmentId, $classId, $exceptCurriculumId)) {
                return 'curriculum.curriculum_exists';
            }
        } elseif ($departmentId !== null
            && $this->curricula->activeCurriculumExists($schoolId, $academicYearId, $gradeLevelId, $departmentId, $exceptCurriculumId)) {
            return 'curriculum.curriculum_exists';
        }
        if ($specializationId !== null) {
            if ($specializationId < 1 || ! $this->specializations->activeInSchool($schoolId, $specializationId)) {
                return 'curriculum.specialization_invalid';
            }
        }

        return null;
    }

    private function placeError(
        int $schoolId,
        int $academicYearId,
        int $gradeLevelId,
        ?int $departmentId,
        ?int $branchId,
        ?int $classId,
        ?int $sectionId,
    ): ?string {
        if ($branchId !== null && ($branchId < 1 || ! $this->curricula->branchActiveInSchool($schoolId, $branchId))) {
            return 'curriculum.branch_invalid';
        }
        if ($branchId !== null && $departmentId !== null && ! $this->curricula->departmentActiveInBranch($departmentId, $branchId)) {
            return 'curriculum.department_not_in_branch';
        }
        if ($sectionId !== null && ($classId === null || $branchId === null)) {
            return 'curriculum.section_needs_class';
        }
        if ($classId !== null) {
            $classGrade = $classId < 1 ? null : $this->curricula->activeClassGradeLevel($schoolId, $academicYearId, $classId);
            if ($classGrade === null) {
                return 'curriculum.class_invalid';
            }
            if ($classGrade !== $gradeLevelId) {
                return 'curriculum.class_grade_mismatch';
            }
        }
        if ($sectionId !== null && $classId !== null
            && ($sectionId < 1 || ! $this->curricula->sectionActiveInClass($sectionId, $classId))) {
            return 'curriculum.section_invalid';
        }

        return null;
    }
}
