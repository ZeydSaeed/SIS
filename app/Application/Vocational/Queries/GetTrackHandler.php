<?php

namespace App\Application\Vocational\Queries;

use App\Application\Vocational\DTOs\TrackDTO;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;

final class GetTrackHandler
{
    public function __construct(
        private readonly VocationalCatalogRepositoryInterface $catalog,
    ) {}

    public function handle(GetTrackQuery $query): ?TrackDTO
    {
        $row = $this->catalog->findTrack($query->schoolId, $query->trackId);
        if ($row === null) {
            return null;
        }

        return new TrackDTO(
            id: $row->id,
            specializationId: $row->specializationId,
            schoolId: $row->schoolId,
            code: $row->code,
            name: $row->name,
            status: $row->status,
            createdAt: $row->createdAt,
            updatedAt: $row->updatedAt,
        );
    }
}
