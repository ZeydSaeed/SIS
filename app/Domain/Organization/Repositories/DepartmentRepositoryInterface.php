<?php

namespace App\Domain\Organization\Repositories;

use App\Domain\Organization\Data\DepartmentSnapshot;

interface DepartmentRepositoryInterface
{
    /**
     * @return list<DepartmentSnapshot>
     */
    public function listForSchool(int $schoolId, ?int $branchId = null): array;

    public function findForSchool(int $schoolId, int $departmentId): ?DepartmentSnapshot;
}
