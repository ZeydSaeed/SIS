<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Contracts\SpecializationCatalogPort;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class UpdateCurriculumGuard
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly SpecializationCatalogPort $specializations,
    ) {}

    /**
     * @param  array{name?: string, specialization_id?: ?int, department_id?: ?int}  $fields
     */
    public function rejectionCode(int $schoolId, int $curriculumId, array $fields): ?string
    {
        if ($this->curricula->findActiveInSchool($schoolId, $curriculumId) === null) {
            return 'curriculum.curriculum_not_found';
        }
        if ($fields === []) {
            return 'curriculum.curriculum_update_empty';
        }
        if (array_key_exists('name', $fields) && trim((string) $fields['name']) === '') {
            return 'curriculum.curriculum_name_invalid';
        }
        if (array_key_exists('department_id', $fields) && $fields['department_id'] !== null) {
            $departmentId = (int) $fields['department_id'];
            if ($departmentId < 1 || ! $this->curricula->departmentActiveInSchool($schoolId, $departmentId)) {
                return 'curriculum.department_invalid';
            }
            $current = $this->curricula->findActiveInSchool($schoolId, $curriculumId);
            if ($current !== null && $this->curricula->activeCurriculumExists($schoolId, $current->academicYearId, $current->gradeLevelId, $departmentId, $curriculumId)) {
                return 'curriculum.curriculum_exists';
            }
        }
        if (array_key_exists('specialization_id', $fields) && $fields['specialization_id'] !== null) {
            $specId = (int) $fields['specialization_id'];
            if ($specId < 1 || ! $this->specializations->activeInSchool($schoolId, $specId)) {
                return 'curriculum.specialization_invalid';
            }
        }

        return null;
    }
}
