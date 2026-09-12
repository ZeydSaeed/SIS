<?php

namespace App\Application\Vocational\Queries;

use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Application\Vocational\DTOs\SpecializationDTO;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;

final class GetSpecializationHandler implements QueryHandler
{
    public function __construct(
        private readonly VocationalCatalogRepositoryInterface $catalog,
    ) {}

    public function handle(Query $query): ?SpecializationDTO
    {
        assert($query instanceof GetSpecializationQuery);

        $row = $this->catalog->findSpecialization($query->schoolId, $query->specializationId, true);
        if ($row === null) {
            return null;
        }

        return new SpecializationDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            code: $row->code,
            name: $row->name,
            description: $row->description,
            status: $row->status,
            tracks: $row->tracks,
            subjectLinks: $row->subjectLinks,
        );
    }
}
