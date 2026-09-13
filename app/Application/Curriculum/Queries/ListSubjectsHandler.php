<?php

namespace App\Application\Curriculum\Queries;

use App\Application\Curriculum\DTOs\SubjectDTO;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;

final class ListSubjectsHandler
{
    public function __construct(
        private readonly SubjectRepositoryInterface $subjects,
    ) {}

    /**
     * @return list<SubjectDTO>
     */
    public function handle(ListSubjectsQuery $query): array
    {
        return array_map(
            fn ($row): SubjectDTO => new SubjectDTO(
                id: $row->id,
                code: $row->code,
                name: $row->name,
                nameEn: $row->nameEn,
                subjectType: $row->subjectType,
                creditHours: $row->creditHours,
                maxGrade: $row->maxGrade,
                passGrade: $row->passGrade,
                status: $row->status,
            ),
            $this->subjects->listActive(),
        );
    }
}
