<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\CurriculumDTO;
use App\Domain\Curriculum\Repositories\CurriculumRepositoryInterface;

final class ListCurriculaHandler
{
    public function __construct(
        private readonly CurriculumRepositoryInterface $curricula,
    ) {}

    /** @return list<CurriculumDTO> */
    public function handle(ListCurriculaQuery $query): array
    {
        return array_map(
            fn ($row): CurriculumDTO => new CurriculumDTO(
                id: $row->id,
                schoolId: $row->schoolId,
                academicYearId: $row->academicYearId,
                gradeLevelId: $row->gradeLevelId,
                name: $row->name,
                status: $row->status,
            ),
            $this->curricula->listActiveForSchool($query->schoolId, $query->academicYearId),
        );
    }
}
