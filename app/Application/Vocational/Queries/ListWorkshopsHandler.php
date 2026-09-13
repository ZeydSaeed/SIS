<?php

namespace App\Application\Vocational\Queries;

use App\Application\Vocational\DTOs\WorkshopDTO;
use App\Domain\Vocational\Repositories\WorkshopRepositoryInterface;

final class ListWorkshopsHandler
{
    public function __construct(
        private readonly WorkshopRepositoryInterface $workshops,
    ) {}

    /**
     * @return list<WorkshopDTO>
     */
    public function handle(ListWorkshopsQuery $query): array
    {
        return array_map(
            static fn ($s): WorkshopDTO => new WorkshopDTO(
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
            ),
            $this->workshops->listBySchool($query->schoolId, $query->status),
        );
    }
}
