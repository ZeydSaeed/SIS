<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Database\SchemaHelper;
use App\Domain\Organization\Data\DepartmentSnapshot;
use App\Domain\Organization\Repositories\DepartmentRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentDepartmentRepository implements DepartmentRepositoryInterface
{
    public function listForSchool(int $schoolId, ?int $branchId = null): array
    {
        $query = DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('school_id', $schoolId)
            ->orderBy('code')
            ->orderBy('id');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->get([
                'id',
                'school_id',
                'branch_id',
                'code',
                'name',
                'department_type',
                'status',
                'created_at',
                'updated_at',
            ])
            ->map(static fn (object $row): DepartmentSnapshot => self::map($row))
            ->all();
    }

    public function findForSchool(int $schoolId, int $departmentId): ?DepartmentSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('school_id', $schoolId)
            ->where('id', $departmentId)
            ->first([
                'id',
                'school_id',
                'branch_id',
                'code',
                'name',
                'department_type',
                'status',
                'created_at',
                'updated_at',
            ]);

        return $row === null ? null : self::map($row);
    }

    private static function map(object $row): DepartmentSnapshot
    {
        return new DepartmentSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            branchId: $row->branch_id !== null ? (int) $row->branch_id : null,
            code: (string) $row->code,
            name: (string) $row->name,
            departmentType: (int) $row->department_type,
            status: (int) $row->status,
            createdAt: (string) $row->created_at,
            updatedAt: (string) $row->updated_at,
        );
    }
}
