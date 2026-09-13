<?php

namespace App\Infrastructure\Enrollment;

use App\Domain\Curriculum\Repositories\PrerequisiteRepositoryInterface;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Enrollment\Contracts\PrerequisiteCatalogPort;

final class CurriculumPrerequisiteCatalogAdapter implements PrerequisiteCatalogPort
{
    public function __construct(
        private readonly SubjectRepositoryInterface $subjects,
        private readonly PrerequisiteRepositoryInterface $prerequisites,
    ) {}

    public function subjectIsActive(int $subjectId): bool
    {
        return $this->subjects->findActive($subjectId) !== null;
    }

    public function activePrerequisiteSubjectIds(int $subjectId): array
    {
        return $this->prerequisites->activePrerequisiteSubjectIds($subjectId);
    }
}
