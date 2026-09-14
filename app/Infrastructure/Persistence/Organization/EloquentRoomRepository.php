<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Database\SchemaHelper;
use App\Domain\Organization\Data\RoomSnapshot;
use App\Domain\Organization\Repositories\RoomRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentRoomRepository implements RoomRepositoryInterface
{
    public function listForSchool(int $schoolId, ?int $branchId = null): array
    {
        $query = DB::table(SchemaHelper::qualified('organization', 'rooms').' as r')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
            ->where('b.school_id', $schoolId)
            ->orderBy('r.code')
            ->orderBy('r.id');

        if ($branchId !== null) {
            $query->where('r.branch_id', $branchId);
        }

        return $query
            ->get([
                'r.id',
                'r.branch_id',
                'b.school_id',
                'r.code',
                'r.name',
                'r.capacity',
                'r.room_type',
                'r.status',
                'r.created_at',
                'r.updated_at',
            ])
            ->map(static fn (object $row): RoomSnapshot => new RoomSnapshot(
                id: (int) $row->id,
                branchId: (int) $row->branch_id,
                schoolId: (int) $row->school_id,
                code: (string) $row->code,
                name: (string) $row->name,
                capacity: $row->capacity !== null ? (int) $row->capacity : null,
                roomType: (int) $row->room_type,
                status: (int) $row->status,
                createdAt: (string) $row->created_at,
                updatedAt: (string) $row->updated_at,
            ))
            ->all();
    }
}
