<?php

namespace App\Domain\Academic\Repositories;

use App\Domain\Academic\Data\TermSnapshot;

interface TermRepositoryInterface
{
    /**
     * @return list<TermSnapshot>
     */
    public function listAll(?int $academicYearId = null): array;

    public function findById(int $id): ?TermSnapshot;
}
