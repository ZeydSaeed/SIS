<?php

namespace App\Application\Organization\Queries;

use App\Application\Organization\DTOs\RoomDTO;
use App\Domain\Organization\Repositories\RoomRepositoryInterface;

final class ListRoomsHandler
{
    public function __construct(
        private readonly RoomRepositoryInterface $rooms,
    ) {}

    /**
     * @return list<RoomDTO>
     */
    public function handle(ListRoomsQuery $query): array
    {
        return array_map(
            static fn ($row): RoomDTO => new RoomDTO(
                id: $row->id,
                branchId: $row->branchId,
                schoolId: $row->schoolId,
                code: $row->code,
                name: $row->name,
                capacity: $row->capacity,
                roomType: $row->roomType,
                status: $row->status,
                createdAt: $row->createdAt,
                updatedAt: $row->updatedAt,
            ),
            $this->rooms->listForSchool($query->schoolId, $query->branchId),
        );
    }
}
