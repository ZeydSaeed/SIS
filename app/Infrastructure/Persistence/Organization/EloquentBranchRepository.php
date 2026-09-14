<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Database\SchemaHelper;
use App\Domain\Organization\Data\BranchSnapshot;
use App\Domain\Organization\Repositories\BranchRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentBranchRepository implements BranchRepositoryInterface
{
    public function listForSchool(int $schoolId): array
    {
        return DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('school_id', $schoolId)
            ->orderBy('code')
            ->orderBy('id')
            ->get([
                'id',
                'school_id',
                'code',
                'name',
                'address',
                'status',
                'created_at',
                'updated_at',
            ])
            ->map(static fn (object $row): BranchSnapshot => new BranchSnapshot(
                id: (int) $row->id,
                schoolId: (int) $row->school_id,
                code: (string) $row->code,
                name: (string) $row->name,
                address: $row->address !== null ? (string) $row->address : null,
                status: (int) $row->status,
                createdAt: (string) $row->created_at,
                updatedAt: (string) $row->updated_at,
            ))
            ->all();
    }
}
