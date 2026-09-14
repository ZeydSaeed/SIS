<?php

namespace App\Application\Organization\Queries;

use App\Application\Organization\DTOs\RoomDTO;
use App\Domain\Organization\Repositories\RoomRepositoryInterface;

final class GetRoomHandler
{
    public function __construct(
        private readonly RoomRepositoryInterface $rooms,
    ) {}

    public function handle(GetRoomQuery $query): ?RoomDTO
    {
        $row = $this->rooms->findForSchool($query->schoolId, $query->roomId);
        if ($row === null) {
            return null;
        }

        return new RoomDTO(
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
        );
    }
}
