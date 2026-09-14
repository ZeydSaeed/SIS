<?php

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Data\RoomSnapshot;

interface RoomRepositoryInterface
{
    /**
     * @return list<RoomSnapshot>
     */
    public function listForSchool(int $schoolId, ?int $branchId = null): array;

    public function findForSchool(int $schoolId, int $roomId): ?RoomSnapshot;
}
