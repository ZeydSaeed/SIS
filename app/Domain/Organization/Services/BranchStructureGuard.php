<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface as Structure;

/**
 * Rules of «الفروع والاختصاصات»: names are required and unique among non-archived rows
 * (branch per school, department per branch); status is نشط / غير نشط / مؤرشف.
 * A branch leaves «نشط» (inactive, archived or deleted) only without active departments
 * or active enrollments; a department in use neither leaves «نشط» nor moves branch.
 */
final class BranchStructureGuard
{
    public function __construct(
        private readonly Structure $structure,
    ) {}

    public function createBranchRejection(int $schoolId, string $name, int $status = Structure::ACTIVE): ?string
    {
        if (! in_array($status, Structure::STATUSES, true)) {
            return 'organization.status_invalid';
        }
        if (trim($name) === '') {
            return 'organization.branch_name_invalid';
        }

        return $this->structure->activeBranchNameTaken($schoolId, trim($name)) ? 'organization.branch_name_taken' : null;
    }

    public function updateBranchRejection(int $schoolId, int $branchId, string $name, int $status = Structure::ACTIVE): ?string
    {
        if (! in_array($status, Structure::STATUSES, true)) {
            return 'organization.status_invalid';
        }
        if ($this->structure->findBranch($schoolId, $branchId) === null) {
            return 'organization.branch_not_found';
        }
        if (trim($name) === '') {
            return 'organization.branch_name_invalid';
        }
        if ($status !== Structure::ARCHIVED && $this->structure->activeBranchNameTaken($schoolId, trim($name), $branchId)) {
            return 'organization.branch_name_taken';
        }

        return $status === Structure::ACTIVE ? null : $this->branchLeavesActiveRejection($branchId);
    }

    /** «حذف» = archive. */
    public function deleteBranchRejection(int $schoolId, int $branchId): ?string
    {
        $branch = $this->structure->findBranch($schoolId, $branchId);
        if ($branch === null || $branch['status'] === Structure::ARCHIVED) {
            return 'organization.branch_not_found';
        }

        return $this->branchLeavesActiveRejection($branchId);
    }

    public function createDepartmentRejection(int $schoolId, int $branchId, string $name, int $status = Structure::ACTIVE): ?string
    {
        if (! in_array($status, Structure::STATUSES, true)) {
            return 'organization.status_invalid';
        }
        $branch = $this->structure->findBranch($schoolId, $branchId);
        if ($branch === null || $branch['status'] === Structure::ARCHIVED) {
            return 'organization.branch_not_found';
        }
        // An active department needs an active branch.
        if ($status === Structure::ACTIVE && $branch['status'] !== Structure::ACTIVE) {
            return 'organization.branch_not_active';
        }
        if (trim($name) === '') {
            return 'organization.department_name_invalid';
        }

        return $this->structure->activeDepartmentNameTaken($branchId, trim($name)) ? 'organization.department_name_taken' : null;
    }

    public function updateDepartmentRejection(int $schoolId, int $departmentId, int $branchId, string $name, int $status = Structure::ACTIVE): ?string
    {
        if (! in_array($status, Structure::STATUSES, true)) {
            return 'organization.status_invalid';
        }
        $department = $this->structure->findDepartment($schoolId, $departmentId);
        if ($department === null) {
            return 'organization.department_not_found';
        }
        $branch = $this->structure->findBranch($schoolId, $branchId);
        if ($branch === null || $branch['status'] === Structure::ARCHIVED) {
            return 'organization.branch_not_found';
        }
        if ($status === Structure::ACTIVE && $branch['status'] !== Structure::ACTIVE) {
            return 'organization.branch_not_active';
        }
        if (trim($name) === '') {
            return 'organization.department_name_invalid';
        }
        if ($status !== Structure::ARCHIVED && $this->structure->activeDepartmentNameTaken($branchId, trim($name), $departmentId)) {
            return 'organization.department_name_taken';
        }
        // Enrollments keep (branch, department) together — a used department stays active in its branch.
        $leavesActive = $department['status'] === Structure::ACTIVE && $status !== Structure::ACTIVE;
        if (($department['branch_id'] !== $branchId || $leavesActive) && $this->structure->departmentInUse($departmentId)) {
            return 'organization.department_in_use';
        }

        return null;
    }

    /** «حذف» = archive. */
    public function deleteDepartmentRejection(int $schoolId, int $departmentId): ?string
    {
        $department = $this->structure->findDepartment($schoolId, $departmentId);
        if ($department === null || $department['status'] === Structure::ARCHIVED) {
            return 'organization.department_not_found';
        }

        return $this->structure->departmentInUse($departmentId) ? 'organization.department_in_use' : null;
    }

    private function branchLeavesActiveRejection(int $branchId): ?string
    {
        if ($this->structure->activeDepartmentCount($branchId) > 0) {
            return 'organization.branch_has_departments';
        }

        return $this->structure->branchInUse($branchId) ? 'organization.branch_in_use' : null;
    }
}
