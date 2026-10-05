<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface as Structure;

/**
 * Rules of «الفروع والاختصاصات»: names are required and unique (branch per school,
 * department per branch); a branch is deleted only without active departments or
 * enrollments; a department in use is neither deleted nor moved to another branch.
 */
final class BranchStructureGuard
{
    public function __construct(
        private readonly Structure $structure,
    ) {}

    public function createBranchRejection(int $schoolId, string $name): ?string
    {
        if (trim($name) === '') {
            return 'organization.branch_name_invalid';
        }

        return $this->structure->activeBranchNameTaken($schoolId, trim($name)) ? 'organization.branch_name_taken' : null;
    }

    public function updateBranchRejection(int $schoolId, int $branchId, string $name): ?string
    {
        if (! $this->isActiveBranch($schoolId, $branchId)) {
            return 'organization.branch_not_found';
        }
        if (trim($name) === '') {
            return 'organization.branch_name_invalid';
        }

        return $this->structure->activeBranchNameTaken($schoolId, trim($name), $branchId) ? 'organization.branch_name_taken' : null;
    }

    public function deleteBranchRejection(int $schoolId, int $branchId): ?string
    {
        if (! $this->isActiveBranch($schoolId, $branchId)) {
            return 'organization.branch_not_found';
        }
        if ($this->structure->activeDepartmentCount($branchId) > 0) {
            return 'organization.branch_has_departments';
        }

        return $this->structure->branchInUse($branchId) ? 'organization.branch_in_use' : null;
    }

    public function createDepartmentRejection(int $schoolId, int $branchId, string $name): ?string
    {
        if (! $this->isActiveBranch($schoolId, $branchId)) {
            return 'organization.branch_not_found';
        }
        if (trim($name) === '') {
            return 'organization.department_name_invalid';
        }

        return $this->structure->activeDepartmentNameTaken($branchId, trim($name)) ? 'organization.department_name_taken' : null;
    }

    public function updateDepartmentRejection(int $schoolId, int $departmentId, int $branchId, string $name): ?string
    {
        $department = $this->structure->findDepartment($schoolId, $departmentId);
        if ($department === null || $department['status'] !== Structure::ACTIVE) {
            return 'organization.department_not_found';
        }
        if (! $this->isActiveBranch($schoolId, $branchId)) {
            return 'organization.branch_not_found';
        }
        if (trim($name) === '') {
            return 'organization.department_name_invalid';
        }
        if ($this->structure->activeDepartmentNameTaken($branchId, trim($name), $departmentId)) {
            return 'organization.department_name_taken';
        }
        // Enrollments keep (branch, department) together — a used department stays in its branch.
        if ($department['branch_id'] !== $branchId && $this->structure->departmentInUse($departmentId)) {
            return 'organization.department_in_use';
        }

        return null;
    }

    public function deleteDepartmentRejection(int $schoolId, int $departmentId): ?string
    {
        $department = $this->structure->findDepartment($schoolId, $departmentId);
        if ($department === null || $department['status'] !== Structure::ACTIVE) {
            return 'organization.department_not_found';
        }

        return $this->structure->departmentInUse($departmentId) ? 'organization.department_in_use' : null;
    }

    private function isActiveBranch(int $schoolId, int $branchId): bool
    {
        $branch = $this->structure->findBranch($schoolId, $branchId);

        return $branch !== null && $branch['status'] === Structure::ACTIVE;
    }
}
