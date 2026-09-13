<?php

namespace App\Domain\Vocational\Repositories;

use App\Domain\Vocational\Data\WorkshopEquipmentSnapshot;

interface WorkshopEquipmentRepositoryInterface
{
    public function workshopBelongsToSchool(int $schoolId, int $workshopId): bool;

    public function codeExists(int $schoolId, int $workshopId, string $code): bool;

    public function create(
        int $schoolId,
        int $workshopId,
        string $code,
        string $name,
        int $quantity,
        int $status,
        string $at,
    ): int;

    /** @return list<WorkshopEquipmentSnapshot> */
    public function listByWorkshop(int $schoolId, int $workshopId, ?int $status = null): array;

    public function find(int $schoolId, int $equipmentId): ?WorkshopEquipmentSnapshot;

    public function setStatus(int $schoolId, int $equipmentId, int $status, string $at): bool;
}
