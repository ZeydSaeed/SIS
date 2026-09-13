<?php

namespace App\Domain\Curriculum\Services;

use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;

final class CreateCurriculumGuard
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
    ) {}

    public function rejectionCode(int $academicYearId, int $gradeLevelId, string $name): ?string
    {
        if (trim($name) === '') {
            return 'curriculum.curriculum_name_invalid';
        }
        if ($academicYearId < 1 || ! $this->curricula->academicYearExists($academicYearId)) {
            return 'curriculum.academic_year_invalid';
        }
        if ($gradeLevelId < 1 || ! $this->curricula->gradeLevelExists($gradeLevelId)) {
            return 'curriculum.grade_level_invalid';
        }

        return null;
    }
}
