<?php

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Data\RoomSnapshot;

interface RoomRepositoryInterface
{
    /**
     * @return list<RoomSnapshot>
     */
    public function listForSchool(int $schoolId, ?int $branchId = null): array;
}
