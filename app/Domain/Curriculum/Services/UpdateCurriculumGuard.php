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
     * @param  array{name?: string, specialization_id?: ?int}  $fields
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
        if (array_key_exists('specialization_id', $fields) && $fields['specialization_id'] !== null) {
            $specId = (int) $fields['specialization_id'];
            if ($specId < 1 || ! $this->specializations->activeInSchool($schoolId, $specId)) {
                return 'curriculum.specialization_invalid';
            }
        }

        return null;
    }
}
