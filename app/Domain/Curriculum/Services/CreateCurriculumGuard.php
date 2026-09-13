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

    public function rejectionCode(
        int $schoolId,
        int $academicYearId,
        int $gradeLevelId,
        string $name,
        ?int $specializationId,
    ): ?string {
        if (trim($name) === '') {
            return 'curriculum.curriculum_name_invalid';
        }
        if ($academicYearId < 1 || ! $this->curricula->academicYearExists($academicYearId)) {
            return 'curriculum.academic_year_invalid';
        }
        if ($gradeLevelId < 1 || ! $this->curricula->gradeLevelExists($gradeLevelId)) {
            return 'curriculum.grade_level_invalid';
        }
        if ($specializationId !== null) {
            if ($specializationId < 1 || ! $this->specializations->activeInSchool($schoolId, $specializationId)) {
                return 'curriculum.specialization_invalid';
            }
        }

        return null;
    }
}
