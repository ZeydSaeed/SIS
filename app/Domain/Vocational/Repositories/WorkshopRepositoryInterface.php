<?php

namespace App\Domain\Vocational\Repositories;

use App\Domain\Vocational\Data\WorkshopSnapshot;

interface WorkshopRepositoryInterface
{
    public function codeExists(int $schoolId, string $code): bool;

    public function create(
        int $schoolId,
        string $code,
        string $name,
        int $capacity,
        int $safetyCapacity,
        ?int $roomId,
        int $status,
        string $at,
    ): int;

    /**
     * @return list<WorkshopSnapshot>
     */
    public function listBySchool(int $schoolId, ?int $status = null): array;
}
