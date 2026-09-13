<?php

namespace App\Infrastructure\Persistence\Vocational;

use App\Database\SchemaHelper;
use App\Domain\Vocational\Data\WorkshopSnapshot;
use App\Domain\Vocational\Repositories\WorkshopRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentWorkshopRepository implements WorkshopRepositoryInterface
{
    private const COLUMNS = [
        'id',
        'school_id',
        'code',
        'name',
        'capacity',
        'safety_capacity',
        'room_id',
        'status',
        'created_at',
        'updated_at',
    ];

    public function codeExists(int $schoolId, string $code): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('vocational', 'workshops'))
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->exists();
    }

    public function create(
        int $schoolId,
        string $code,
        string $name,
        int $capacity,
        int $safetyCapacity,
        ?int $roomId,
        int $status,
        string $at,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('vocational', 'workshops'))->insertGetId([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'capacity' => $capacity,
            'safety_capacity' => $safetyCapacity,
            'room_id' => $roomId,
            'status' => $status,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function listBySchool(int $schoolId, ?int $status = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('vocational', 'workshops'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get(self::COLUMNS)
            ->map(static function (object $row): WorkshopSnapshot {
                return new WorkshopSnapshot(
                    id: (int) $row->id,
                    schoolId: (int) $row->school_id,
                    code: (string) $row->code,
                    name: (string) $row->name,
                    capacity: (int) $row->capacity,
                    safetyCapacity: (int) $row->safety_capacity,
                    roomId: $row->room_id !== null ? (int) $row->room_id : null,
                    status: (int) $row->status,
                    createdAt: (string) $row->created_at,
                    updatedAt: (string) $row->updated_at,
                );
            })
            ->all();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
