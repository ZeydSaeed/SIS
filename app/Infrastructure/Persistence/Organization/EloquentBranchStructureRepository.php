<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Database\SchemaHelper;
use App\Domain\Organization\Repositories\BranchStructureRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentBranchStructureRepository implements BranchStructureRepositoryInterface
{
    public function structure(int $schoolId, bool $activeOnly = true): array
    {
        return $this->structureBySchool([$schoolId], $activeOnly)[$schoolId] ?? [];
    }

    public function structureBySchool(array $schoolIds, bool $activeOnly = true): array
    {
        if ($schoolIds === []) {
            return [];
        }

        $branches = DB::table($this->branches())
            ->whereIn('school_id', $schoolIds)
            ->when($activeOnly, fn (Builder $query) => $query->where('status', self::ACTIVE))
            ->orderBy('id')
            ->get(['id', 'school_id', 'code', 'name', 'description', 'status', 'abbreviation', 'color_hue']);
        if ($branches->isEmpty()) {
            return [];
        }

        $departments = DB::table($this->departments())
            ->whereIn('school_id', $schoolIds)
            ->whereIn('branch_id', $branches->pluck('id'))
            ->when($activeOnly, fn (Builder $query) => $query->where('status', self::ACTIVE))
            ->orderBy('id')
            ->get(['id', 'branch_id', 'code', 'name', 'description', 'status', 'abbreviation', 'color_hue']);

        $byBranch = [];
        foreach ($departments as $row) {
            $byBranch[(int) $row->branch_id][] = [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'description' => $row->description !== null ? (string) $row->description : null,
                'status' => (int) $row->status,
                'abbreviation' => $row->abbreviation !== null ? (string) $row->abbreviation : null,
                'color_hue' => $row->color_hue !== null ? (int) $row->color_hue : null,
            ];
        }

        $bySchool = [];
        foreach ($branches as $row) {
            $bySchool[(int) $row->school_id][] = [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'description' => $row->description !== null ? (string) $row->description : null,
                'status' => (int) $row->status,
                'abbreviation' => $row->abbreviation !== null ? (string) $row->abbreviation : null,
                'color_hue' => $row->color_hue !== null ? (int) $row->color_hue : null,
                'departments' => $byBranch[(int) $row->id] ?? [],
            ];
        }

        return $bySchool;
    }

    public function findBranch(int $schoolId, int $branchId): ?array
    {
        $row = DB::table($this->branches())->where('school_id', $schoolId)->where('id', $branchId)
            ->first(['id', 'school_id', 'name', 'status']);

        return $row === null ? null : [
            'id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'name' => (string) $row->name,
            'status' => (int) $row->status,
        ];
    }

    public function findDepartment(int $schoolId, int $departmentId): ?array
    {
        $row = DB::table($this->departments())->where('school_id', $schoolId)->where('id', $departmentId)
            ->first(['id', 'school_id', 'branch_id', 'name', 'status']);

        return $row === null ? null : [
            'id' => (int) $row->id,
            'school_id' => (int) $row->school_id,
            'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
            'name' => (string) $row->name,
            'status' => (int) $row->status,
        ];
    }

    public function activeBranchNameTaken(int $schoolId, string $name, ?int $exceptBranchId = null): bool
    {
        return DB::table($this->branches())
            ->where('school_id', $schoolId)
            ->where('status', '<>', self::ARCHIVED)
            ->whereRaw('trim(name) = ?', [$name])
            ->when($exceptBranchId !== null, fn (Builder $query) => $query->where('id', '<>', $exceptBranchId))
            ->exists();
    }

    public function activeDepartmentNameTaken(int $branchId, string $name, ?int $exceptDepartmentId = null): bool
    {
        return DB::table($this->departments())
            ->where('branch_id', $branchId)
            ->where('status', '<>', self::ARCHIVED)
            ->whereRaw('trim(name) = ?', [$name])
            ->when($exceptDepartmentId !== null, fn (Builder $query) => $query->where('id', '<>', $exceptDepartmentId))
            ->exists();
    }

    public function activeDepartmentCount(int $branchId): int
    {
        return DB::table($this->departments())->where('branch_id', $branchId)->where('status', self::ACTIVE)->count();
    }

    public function branchInUse(int $branchId): bool
    {
        return $this->activeEnrollments()->where('branch_id', $branchId)->exists();
    }

    public function departmentInUse(int $departmentId): bool
    {
        return $this->activeEnrollments()->where('department_id', $departmentId)->exists()
            || DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
                ->where('department_id', $departmentId)
                ->where('status', self::ACTIVE)
                ->exists();
    }

    public function createBranch(int $schoolId, string $name, ?string $description, int $status = self::ACTIVE): int
    {
        return (int) DB::table($this->branches())->insertGetId([
            'school_id' => $schoolId,
            'code' => OrganizationCodeSequence::next($this->branches(), 'BR'),
            'name' => $name,
            'description' => $description,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateBranch(int $branchId, string $name, ?string $description, int $status): void
    {
        DB::table($this->branches())->where('id', $branchId)
            ->update(['name' => $name, 'description' => $description, 'status' => $status, 'updated_at' => now()]);
    }

    public function setBranchStatus(int $branchId, int $status): void
    {
        DB::table($this->branches())->where('id', $branchId)->update(['status' => $status, 'updated_at' => now()]);
    }

    public function createDepartment(int $schoolId, int $branchId, string $name, ?string $description, int $status = self::ACTIVE): int
    {
        return (int) DB::table($this->departments())->insertGetId([
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'code' => OrganizationCodeSequence::next($this->departments(), 'DEP'),
            'name' => $name,
            'description' => $description,
            // Same type as the seeded catalog (vocational department).
            'department_type' => 1,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function updateDepartment(int $departmentId, int $branchId, string $name, ?string $description, int $status): void
    {
        DB::table($this->departments())->where('id', $departmentId)
            ->update(['branch_id' => $branchId, 'name' => $name, 'description' => $description, 'status' => $status, 'updated_at' => now()]);
    }

    public function setDepartmentStatus(int $departmentId, int $status): void
    {
        DB::table($this->departments())->where('id', $departmentId)->update(['status' => $status, 'updated_at' => now()]);
    }

    private function activeEnrollments(): Builder
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('status', 1)
            ->whereNull('effective_to');
    }

    private function branches(): string
    {
        return SchemaHelper::qualified('organization', 'branches');
    }

    private function departments(): string
    {
        return SchemaHelper::qualified('organization', 'departments');
    }
}
