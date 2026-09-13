<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\CurriculumDTO;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class GetCurriculumHandler
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
    ) {}

    public function handle(GetCurriculumQuery $query): ?CurriculumDTO
    {
        $row = $this->curricula->findActiveInSchool($query->schoolId, $query->curriculumId)
            ?? $this->curricula->findInactiveInSchool($query->schoolId, $query->curriculumId);
        if ($row === null) {
            return null;
        }

        return new CurriculumDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            academicYearId: $row->academicYearId,
            gradeLevelId: $row->gradeLevelId,
            specializationId: $row->specializationId,
            name: $row->name,
            status: $row->status,
        );
    }
}
