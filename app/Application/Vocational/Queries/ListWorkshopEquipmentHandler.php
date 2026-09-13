<?php

namespace App\Application\Vocational\Queries;

use App\Application\Vocational\DTOs\WorkshopEquipmentDTO;
use App\Domain\Vocational\Repositories\WorkshopEquipmentRepositoryInterface;

final class ListWorkshopEquipmentHandler
{
    public function __construct(
        private readonly WorkshopEquipmentRepositoryInterface $equipment,
    ) {}

    /** @return list<WorkshopEquipmentDTO>|null null when workshop missing */
    public function handle(ListWorkshopEquipmentQuery $query): ?array
    {
        if (! $this->equipment->workshopBelongsToSchool($query->schoolId, $query->workshopId)) {
            return null;
        }

        return array_map(
            static fn ($row): WorkshopEquipmentDTO => new WorkshopEquipmentDTO(
                id: $row->id,
                schoolId: $row->schoolId,
                workshopId: $row->workshopId,
                code: $row->code,
                name: $row->name,
                quantity: $row->quantity,
                status: $row->status,
            ),
            $this->equipment->listByWorkshop($query->schoolId, $query->workshopId, $query->status),
        );
    }
}
