<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Repositories\TimetablePlaceRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentTimetablePlaceRepository implements TimetablePlaceRepositoryInterface
{
    private const ACTIVE = 1;

    public function places(int $schoolId): array
    {
        $this->bindSchool($schoolId);
        $rooms = SchemaHelper::qualified('organization', 'rooms');
        $schedules = SchemaHelper::qualified('timetable', 'schedules');
        $activities = SchemaHelper::qualified('timetable', 'activities');
        $workshops = SchemaHelper::qualified('vocational', 'workshops');

        $roomRows = DB::table($rooms.' as r')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
            ->where('b.school_id', $schoolId)
            ->orderBy('b.name')->orderBy('r.code')->orderBy('r.id')
            ->selectRaw("r.id, r.branch_id, b.name as branch_name, r.code, r.name, r.capacity, r.room_type, r.status,
                (SELECT count(*) FROM {$schedules} s WHERE s.room_id = r.id AND s.lifecycle_status = 1)
              + (SELECT count(*) FROM {$activities} a WHERE a.room_id = r.id AND a.status = 1)
              + (SELECT count(*) FROM {$workshops} w WHERE w.room_id = r.id AND w.status = 1) as used")
            ->get();

        $workshopRows = DB::table($workshops.' as w')
            ->where('w.school_id', $schoolId)
            ->orderBy('w.code')->orderBy('w.id')
            ->selectRaw("w.id, w.code, w.name, w.capacity, w.safety_capacity, w.room_id, w.status,
                (SELECT count(*) FROM {$activities} a WHERE a.workshop_id = w.id AND a.status = 1) as used")
            ->get();

        return [
            'rooms' => $roomRows->map(static fn (object $r): array => [
                'id' => (int) $r->id, 'branch_id' => (int) $r->branch_id, 'branch_name' => (string) $r->branch_name,
                'code' => (string) $r->code, 'name' => (string) $r->name,
                'capacity' => $r->capacity !== null ? (int) $r->capacity : null,
                'room_type' => (int) $r->room_type, 'status' => (int) $r->status, 'used' => (int) $r->used,
            ])->all(),
            'workshops' => $workshopRows->map(static fn (object $w): array => [
                'id' => (int) $w->id, 'code' => (string) $w->code, 'name' => (string) $w->name,
                'capacity' => (int) $w->capacity, 'safety_capacity' => (int) $w->safety_capacity,
                'room_id' => $w->room_id !== null ? (int) $w->room_id : null,
                'status' => (int) $w->status, 'used' => (int) $w->used,
            ])->all(),
        ];
    }

    public function findRoom(int $schoolId, int $roomId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('organization', 'rooms').' as r')
            ->join(SchemaHelper::qualified('organization', 'branches').' as b', 'b.id', '=', 'r.branch_id')
            ->where('b.school_id', $schoolId)->where('r.id', $roomId)
            ->first(['r.id', 'r.branch_id', 'r.status']);

        return $row === null ? null : ['id' => (int) $row->id, 'branch_id' => (int) $row->branch_id, 'status' => (int) $row->status];
    }

    public function findWorkshop(int $schoolId, int $workshopId): ?array
    {
        $this->bindSchool($schoolId);
        $row = DB::table(SchemaHelper::qualified('vocational', 'workshops'))
            ->where('school_id', $schoolId)->where('id', $workshopId)->first(['id', 'status']);

        return $row === null ? null : ['id' => (int) $row->id, 'status' => (int) $row->status];
    }

    public function branchActive(int $schoolId, int $branchId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'branches'))
            ->where('id', $branchId)->where('school_id', $schoolId)->where('status', self::ACTIVE)->exists();
    }

    public function roomCodeTaken(int $branchId, string $code, ?int $exceptRoomId = null): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'rooms'))
            ->where('branch_id', $branchId)->where('code', $code)
            ->when($exceptRoomId !== null, fn ($q) => $q->where('id', '<>', $exceptRoomId))
            ->exists();
    }

    public function workshopCodeTaken(int $schoolId, string $code, ?int $exceptWorkshopId = null): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('vocational', 'workshops'))
            ->where('school_id', $schoolId)->where('code', $code)
            ->when($exceptWorkshopId !== null, fn ($q) => $q->where('id', '<>', $exceptWorkshopId))
            ->exists();
    }

    public function roomActiveInSchool(int $schoolId, int $roomId): bool
    {
        $room = $this->findRoom($schoolId, $roomId);

        return $room !== null && $room['status'] === self::ACTIVE;
    }

    public function createRoom(int $branchId, string $code, string $name, ?int $capacity, int $roomType, string $at): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'rooms'))->insertGetId([
            'branch_id' => $branchId, 'code' => strtoupper(trim($code)), 'name' => trim($name), 'capacity' => $capacity,
            'room_type' => $roomType, 'status' => self::ACTIVE, 'created_at' => $at, 'updated_at' => $at,
        ] + self::managedType($roomType));
    }

    public function updateRoom(int $roomId, string $name, ?int $capacity, int $roomType, string $at): void
    {
        DB::table(SchemaHelper::qualified('organization', 'rooms'))->where('id', $roomId)
            ->update(['name' => trim($name), 'capacity' => $capacity, 'room_type' => $roomType, 'supports_practical' => $roomType === 2, 'updated_at' => $at]);
    }

    /**
     * «الغرف الدراسية» owns rooms now; this legacy timetable path keeps the new columns consistent: practical support
     * follows the legacy class and a new room gets the matching system type (classroom / lab).
     *
     * @return array{supports_practical: bool, room_type_id: int|null}
     */
    private static function managedType(int $roomType): array
    {
        $typeId = DB::table(SchemaHelper::qualified('organization', 'room_types'))
            ->whereNull('school_id')->where('code', $roomType === 2 ? 'LAB' : 'CLASSROOM')->value('id');

        return ['supports_practical' => $roomType === 2, 'room_type_id' => $typeId !== null ? (int) $typeId : null];
    }

    public function setRoomStatus(int $roomId, int $status, string $at): void
    {
        DB::table(SchemaHelper::qualified('organization', 'rooms'))->where('id', $roomId)
            ->update(['status' => $status, 'updated_at' => $at]);
    }

    public function createWorkshop(int $schoolId, string $code, string $name, int $capacity, int $safetyCapacity, ?int $roomId, string $at): int
    {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('vocational', 'workshops'))->insertGetId([
            'school_id' => $schoolId, 'code' => strtoupper(trim($code)), 'name' => trim($name), 'capacity' => $capacity,
            'safety_capacity' => $safetyCapacity, 'room_id' => $roomId, 'status' => self::ACTIVE, 'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    public function updateWorkshop(int $workshopId, string $name, int $capacity, int $safetyCapacity, ?int $roomId, string $at): void
    {
        DB::table(SchemaHelper::qualified('vocational', 'workshops'))->where('id', $workshopId)
            ->update(['name' => trim($name), 'capacity' => $capacity, 'safety_capacity' => $safetyCapacity, 'room_id' => $roomId, 'updated_at' => $at]);
    }

    public function setWorkshopStatus(int $workshopId, int $status, string $at): void
    {
        DB::table(SchemaHelper::qualified('vocational', 'workshops'))->where('id', $workshopId)
            ->update(['status' => $status, 'updated_at' => $at]);
    }

    public function roomUsage(int $schoolId, int $roomId): int
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('room_id', $roomId)->where('lifecycle_status', 1)->count()
            + DB::table(SchemaHelper::qualified('timetable', 'activities'))->where('room_id', $roomId)->where('status', 1)->count()
            + DB::table(SchemaHelper::qualified('vocational', 'workshops'))->where('room_id', $roomId)->where('status', 1)->count();
    }

    public function workshopUsage(int $schoolId, int $workshopId): int
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'activities'))->where('workshop_id', $workshopId)->where('status', 1)->count();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
