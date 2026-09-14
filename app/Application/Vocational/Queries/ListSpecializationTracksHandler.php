<?php

namespace App\Application\Vocational\Queries;

use App\Application\Vocational\DTOs\TrackDTO;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;

final class ListSpecializationTracksHandler
{
    public function __construct(
        private readonly VocationalCatalogRepositoryInterface $catalog,
    ) {}

    /**
     * @return list<TrackDTO>|null null when specialization not in school
     */
    public function handle(ListSpecializationTracksQuery $query): ?array
    {
        if ($this->catalog->findSpecialization($query->schoolId, $query->specializationId, withChildren: false) === null) {
            return null;
        }

        return array_map(
            fn ($row): TrackDTO => new TrackDTO(
                id: $row->id,
                specializationId: $row->specializationId,
                schoolId: $row->schoolId,
                code: $row->code,
                name: $row->name,
                status: $row->status,
                createdAt: $row->createdAt,
                updatedAt: $row->updatedAt,
            ),
            $this->catalog->listTracksForSpecialization($query->schoolId, $query->specializationId),
        );
    }
}
