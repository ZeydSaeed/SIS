<?php

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Data\BranchSnapshot;

interface BranchRepositoryInterface
{
    /**
     * @return list<BranchSnapshot>
     */
    public function listForSchool(int $schoolId): array;
}
