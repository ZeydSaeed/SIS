<?php

namespace App\Infrastructure\Persistence\Vocational;

use App\Database\SchemaHelper;
use App\Domain\Vocational\Data\WorkshopEquipmentSnapshot;
use App\Domain\Vocational\Repositories\WorkshopEquipmentRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentWorkshopEquipmentRepository implements WorkshopEquipmentRepositoryInterface
{
    public function workshopBelongsToSchool(int $schoolId, int $workshopId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('vocational', 'workshops'))
            ->where('school_id', $schoolId)
            ->where('id', $workshopId)
            ->exists();
    }

    public function codeExists(int $schoolId, int $workshopId, string $code): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('vocational', 'workshop_equipment'))
            ->where('school_id', $schoolId)
            ->where('workshop_id', $workshopId)
            ->where('code', $code)
            ->exists();
    }

    public function create(
        int $schoolId,
        int $workshopId,
        string $code,
        string $name,
        int $quantity,
        int $status,
        string $at,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('vocational', 'workshop_equipment'))->insertGetId([
            'school_id' => $schoolId,
            'workshop_id' => $workshopId,
            'code' => $code,
            'name' => $name,
            'quantity' => $quantity,
            'status' => $status,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function listByWorkshop(int $schoolId, int $workshopId, ?int $status = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('vocational', 'workshop_equipment'))
            ->where('school_id', $schoolId)
            ->where('workshop_id', $workshopId)
            ->orderBy('id');

        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get(['id', 'school_id', 'workshop_id', 'code', 'name', 'quantity', 'status'])
            ->map(fn ($row): WorkshopEquipmentSnapshot => $this->map($row))
            ->all();
    }

    public function find(int $schoolId, int $equipmentId): ?WorkshopEquipmentSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('vocational', 'workshop_equipment'))
            ->where('school_id', $schoolId)
            ->where('id', $equipmentId)
            ->first(['id', 'school_id', 'workshop_id', 'code', 'name', 'quantity', 'status']);

        return $row === null ? null : $this->map($row);
    }

    public function setStatus(int $schoolId, int $equipmentId, int $status, string $at): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('vocational', 'workshop_equipment'))
            ->where('school_id', $schoolId)
            ->where('id', $equipmentId)
            ->update(['status' => $status, 'updated_at' => $at]) > 0;
    }

    private function map(object $row): WorkshopEquipmentSnapshot
    {
        return new WorkshopEquipmentSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            workshopId: (int) $row->workshop_id,
            code: (string) $row->code,
            name: (string) $row->name,
            quantity: (int) $row->quantity,
            status: (int) $row->status,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
