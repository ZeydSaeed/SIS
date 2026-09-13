<?php

namespace App\Application\Vocational\Queries;

use App\Application\Vocational\DTOs\WorkshopEquipmentDTO;
use App\Domain\Vocational\Repositories\WorkshopEquipmentRepositoryInterface;

final class GetWorkshopEquipmentHandler
{
    public function __construct(
        private readonly WorkshopEquipmentRepositoryInterface $equipment,
    ) {}

    public function handle(GetWorkshopEquipmentQuery $query): ?WorkshopEquipmentDTO
    {
        $s = $this->equipment->find($query->schoolId, $query->equipmentId);
        if ($s === null) {
            return null;
        }

        return new WorkshopEquipmentDTO(
            id: $s->id,
            schoolId: $s->schoolId,
            workshopId: $s->workshopId,
            code: $s->code,
            name: $s->name,
            quantity: $s->quantity,
            status: $s->status,
        );
    }
}
