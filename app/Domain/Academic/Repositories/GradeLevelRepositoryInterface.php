<?php

namespace App\Domain\Academic\Repositories;

use App\Domain\Academic\Data\GradeLevelSnapshot;

interface GradeLevelRepositoryInterface
{
    public function findById(int $id): ?GradeLevelSnapshot;

    /**
     * @return list<GradeLevelSnapshot>
     */
    public function listAll(): array;
}
