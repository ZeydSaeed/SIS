<?php

namespace App\Domain\Organization\Repositories;

/**
 * «الفروع والاختصاصات»: a school's branches (الفروع) and their departments (الاختصاصات).
 * Status 1 = نشط, 2 = غير نشط, 3 = مؤرشف («حذف» archives; rows stay for history).
 */
interface BranchStructureRepositoryInterface
{
    public const ACTIVE = 1;

    public const INACTIVE = 2;

    public const ARCHIVED = 3;

    public const STATUSES = [self::ACTIVE, self::INACTIVE, self::ARCHIVED];

    /**
     * Branches of the school with their departments — active only (forms / lists), or every
     * status for the «الفروع والاختصاصات» management page.
     *
     * @return list<array{id:int, code:string, name:string, description:?string, status:int, departments: list<array{id:int, code:string, name:string, description:?string, status:int}>}>
     */
    public function structure(int $schoolId, bool $activeOnly = true): array;

    /**
     * Active branches (with active departments) of each given school, keyed by school id.
     * Schools without branches are absent.
     *
     * @param  list<int>  $schoolIds
     * @return array<int, list<array{id:int, code:string, name:string, description:?string, departments: list<array{id:int, code:string, name:string, description:?string}>}>>
     */
    public function structureBySchool(array $schoolIds, bool $activeOnly = true): array;

    /** @return array{id:int, school_id:int, name:string, status:int}|null */
    public function findBranch(int $schoolId, int $branchId): ?array;

    /** @return array{id:int, school_id:int, branch_id:?int, name:string, status:int}|null */
    public function findDepartment(int $schoolId, int $departmentId): ?array;

    public function activeBranchNameTaken(int $schoolId, string $name, ?int $exceptBranchId = null): bool;

    public function activeDepartmentNameTaken(int $branchId, string $name, ?int $exceptDepartmentId = null): bool;

    public function activeDepartmentCount(int $branchId): int;

    /** Active enrollments placed in the branch. */
    public function branchInUse(int $branchId): bool;

    /** Active enrollments or active curricula on the department. */
    public function departmentInUse(int $departmentId): bool;

    public function createBranch(int $schoolId, string $name, ?string $description, int $status = self::ACTIVE): int;

    public function updateBranch(int $branchId, string $name, ?string $description, int $status): void;

    public function setBranchStatus(int $branchId, int $status): void;

    public function createDepartment(int $schoolId, int $branchId, string $name, ?string $description, int $status = self::ACTIVE): int;

    public function updateDepartment(int $departmentId, int $branchId, string $name, ?string $description, int $status): void;

    public function setDepartmentStatus(int $departmentId, int $status): void;
}
