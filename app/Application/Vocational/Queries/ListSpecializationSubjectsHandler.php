<?php

namespace App\Application\Vocational\Queries;

use App\Application\Vocational\DTOs\SpecializationSubjectDTO;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;

final class ListSpecializationSubjectsHandler
{
    public function __construct(
        private readonly VocationalCatalogRepositoryInterface $catalog,
    ) {}

    /** @return list<SpecializationSubjectDTO>|null */
    public function handle(ListSpecializationSubjectsQuery $query): ?array
    {
        $rows = $this->catalog->listSpecializationSubjects(
            $query->schoolId,
            $query->specializationId,
            $query->status,
        );

        if ($rows === null) {
            return null;
        }

        return array_map(
            static fn ($row): SpecializationSubjectDTO => new SpecializationSubjectDTO(
                id: $row->id,
                specializationId: $row->specializationId,
                schoolId: $row->schoolId,
                subjectId: $row->subjectId,
                isRequired: $row->isRequired,
                creditHours: $row->creditHours,
                status: $row->status,
            ),
            $rows,
        );
    }
}
