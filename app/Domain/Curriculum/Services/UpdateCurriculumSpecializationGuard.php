<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Contracts\SpecializationCatalogPort;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class UpdateCurriculumSpecializationGuard
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
        private readonly SpecializationCatalogPort $specializations,
    ) {}

    public function rejectionCode(
        int $schoolId,
        int $curriculumId,
        ?int $specializationId,
    ): ?string {
        if ($this->curricula->findActiveInSchool($schoolId, $curriculumId) === null) {
            return 'curriculum.curriculum_not_found';
        }
        if ($specializationId !== null) {
            if ($specializationId < 1 || ! $this->specializations->activeInSchool($schoolId, $specializationId)) {
                return 'curriculum.specialization_invalid';
            }
        }

        return null;
    }
}
