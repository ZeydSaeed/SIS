<?php

namespace App\Application\Vocational\Queries;

use App\Application\Vocational\DTOs\SpecializationSubjectDTO;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;

final class GetSpecializationSubjectHandler
{
    public function __construct(
        private readonly VocationalCatalogRepositoryInterface $catalog,
    ) {}

    public function handle(GetSpecializationSubjectQuery $query): ?SpecializationSubjectDTO
    {
        $row = $this->catalog->findSpecializationSubjectLink($query->schoolId, $query->linkId);
        if ($row === null) {
            return null;
        }

        return new SpecializationSubjectDTO(
            id: $row->id,
            specializationId: $row->specializationId,
            schoolId: $row->schoolId,
            subjectId: $row->subjectId,
            isRequired: $row->isRequired,
            creditHours: $row->creditHours,
            status: $row->status,
        );
    }
}
