<?php

namespace App\Application\Vocational\Queries;

use App\Application\Vocational\DTOs\WorkshopDTO;
use App\Domain\Vocational\Repositories\WorkshopRepositoryInterface;

final class GetWorkshopHandler
{
    public function __construct(
        private readonly WorkshopRepositoryInterface $workshops,
    ) {}

    public function handle(GetWorkshopQuery $query): ?WorkshopDTO
    {
        $s = $this->workshops->find($query->schoolId, $query->workshopId);
        if ($s === null) {
            return null;
        }

        return new WorkshopDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            code: $s->code,
            name: $s->name,
            capacity: $s->capacity,
            safetyCapacity: $s->safetyCapacity,
            roomId: $s->roomId,
            status: $s->status,
            createdAt: $s->createdAt,
            updatedAt: $s->updatedAt,
        );
    }
}
