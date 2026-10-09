<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Database\SchemaHelper;
use App\Domain\Organization\Data\RoomDetails;
use App\Domain\Organization\Repositories\RoomCatalogueRepositoryInterface;
use App\Domain\Shared\ValueObjects\DisplayAppearance;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentRoomCatalogueRepository implements RoomCatalogueRepositoryInterface
{
    /** Sortable columns of the rooms table (whitelist — never interpolate user input). */
    private const SORTS = [
        'code' => 'r.code',
        'name' => 'r.name',
        'room_number' => 'r.room_number',
        'type' => 't.name',
        'capacity' => 'r.capacity',
        'building' => 'r.building',
        'floor' => 'r.floor',
        'branch' => 'b.name',
        'status' => 'r.status',
    ];

    public function page(int $schoolId, array $filters, string $sort, string $direction, int $page, int $perPage): array
    {
        $this->bindSchool($schoolId);
        $query = $this->filtered($schoolId, $filters);
        $total = (clone $query)->count();

        $schedules = SchemaHelper::qualified('timetable', 'schedules');
        $activities = SchemaHelper::qualified('timetable', 'activities');
        $workshops = SchemaHelper::qualified('vocational', 'workshops');
        $column = self::SORTS[$sort] ?? self::SORTS['code'];
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $rows = $query
            ->orderByRaw("{$column} {$direction} NULLS LAST")
            ->orderBy('r.id')
            ->forPage(max(1, $page), $perPage)
            ->selectRaw("r.id, r.branch_id, b.name AS branch_name, r.code, r.name, r.abbreviation, r.color_hue, r.room_number,
                r.room_type_id, t.name AS type_name, t.kind AS type_kind, r.capacity, r.building, r.floor, r.location,
                r.department_id, d.name AS department_name, r.supports_practical, r.equipment, r.suitable_for, r.notes, r.status,
                (SELECT count(*) FROM {$schedules} s WHERE s.room_id = r.id AND s.lifecycle_status = 1) AS weekly_lessons,
                (SELECT count(*) FROM {$activities} a WHERE a.room_id = r.id AND a.status = 1)
              + (SELECT count(*) FROM {$workshops} w WHERE w.room_id = r.id AND w.status = 1) AS links")
            ->get();

        return [
            'rows' => $rows->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'branch_id' => (int) $r->branch_id,
                'branch_name' => (string) $r->branch_name,
                'code' => (string) $r->code,
                'name' => (string) $r->name,
                'abbreviation' => $r->abbreviation !== null ? (string) $r->abbreviation : null,
                'color_hue' => $r->color_hue !== null ? (int) $r->color_hue : null,
                'room_number' => $r->room_number !== null ? (string) $r->room_number : null,
                'room_type_id' => $r->room_type_id !== null ? (int) $r->room_type_id : null,
                'type_name' => $r->type_name !== null ? (string) $r->type_name : null,
                'type_kind' => $r->type_kind !== null ? (int) $r->type_kind : null,
                'capacity' => $r->capacity !== null ? (int) $r->capacity : null,
                'building' => $r->building !== null ? (string) $r->building : null,
                'floor' => $r->floor !== null ? (int) $r->floor : null,
                'location' => $r->location !== null ? (string) $r->location : null,
                'department_id' => $r->department_id !== null ? (int) $r->department_id : null,
                'department_name' => $r->department_name !== null ? (string) $r->department_name : null,
                'supports_practical' => (bool) $r->supports_practical,
                'equipment' => $r->equipment !== null ? (string) $r->equipment : null,
                'suitable_for' => $r->suitable_for !== null ? (string) $r->suitable_for : null,
                'notes' => $r->notes !== null ? (string) $r->notes : null,
                'status' => (int) $r->status,
                'weekly_lessons' => (int) $r->weekly_lessons,
                'links' => (int) $r->links,
            ])->all(),
            'total' => $total,
        ];
    }

    public function stats(int $schoolId): array
    {
        $row = DB::table($this->rooms().' as r')
            ->join($this->branches().' as b', 'b.id', '=', 'r.branch_id')
            ->leftJoin($this->typeTable().' as t', 't.id', '=', 'r.room_type_id')
            ->where('b.school_id', $schoolId)
            ->selectRaw('count(*) AS total,
                count(*) FILTER (WHERE r.status = 1) AS active,
                count(*) FILTER (WHERE r.status = 1 AND r.supports_practical) AS practical,
                COALESCE(sum(r.capacity) FILTER (WHERE r.status = 1), 0) AS capacity')
            ->first();
        $byKind = DB::table($this->rooms().' as r')
            ->join($this->branches().' as b', 'b.id', '=', 'r.branch_id')
            ->leftJoin($this->typeTable().' as t', 't.id', '=', 'r.room_type_id')
            ->where('b.school_id', $schoolId)->where('r.status', self::ACTIVE)
            ->groupBy('t.kind')
            ->selectRaw('COALESCE(t.kind, 9) AS kind, count(*) AS n')
            ->pluck('n', 'kind');

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active ?? 0),
            'practical' => (int) ($row->practical ?? 0),
            'capacity' => (int) ($row->capacity ?? 0),
            'by_kind' => array_map('intval', $byKind->all()),
        ];
    }

    public function types(int $schoolId): array
    {
        $rooms = $this->rooms();
        $branches = $this->branches();

        return DB::table($this->typeTable().' as t')
            ->where(fn (Builder $q) => $q->whereNull('t.school_id')->orWhere('t.school_id', $schoolId))
            ->orderBy('t.sort_order')->orderBy('t.name')->orderBy('t.id')
            ->selectRaw("t.id, t.school_id, t.code, t.name, t.abbreviation, t.kind, t.supports_practical, t.color_hue, t.status,
                (SELECT count(*) FROM {$rooms} r JOIN {$branches} b ON b.id = r.branch_id
                 WHERE r.room_type_id = t.id AND b.school_id = ? AND r.status = 1) AS rooms", [$schoolId])
            ->get()
            ->map(static fn (object $t): array => [
                'id' => (int) $t->id,
                'school_id' => $t->school_id !== null ? (int) $t->school_id : null,
                'code' => (string) $t->code,
                'name' => (string) $t->name,
                'abbreviation' => $t->abbreviation !== null ? (string) $t->abbreviation : null,
                'kind' => (int) $t->kind,
                'supports_practical' => (bool) $t->supports_practical,
                'color_hue' => $t->color_hue !== null ? (int) $t->color_hue : null,
                'status' => (int) $t->status,
                'rooms' => (int) $t->rooms,
            ])->all();
    }

    public function findRoom(int $schoolId, int $roomId): ?array
    {
        $row = DB::table($this->rooms().' as r')
            ->join($this->branches().' as b', 'b.id', '=', 'r.branch_id')
            ->where('b.school_id', $schoolId)->where('r.id', $roomId)
            ->first(['r.id', 'r.branch_id', 'r.status']);

        return $row === null ? null : ['id' => (int) $row->id, 'branch_id' => (int) $row->branch_id, 'status' => (int) $row->status];
    }

    public function findType(int $schoolId, int $typeId): ?array
    {
        $row = DB::table($this->typeTable())
            ->where('id', $typeId)
            ->where(fn (Builder $q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId))
            ->first(['id', 'school_id', 'kind', 'status']);

        return $row === null ? null : [
            'id' => (int) $row->id,
            'school_id' => $row->school_id !== null ? (int) $row->school_id : null,
            'kind' => (int) $row->kind,
            'status' => (int) $row->status,
        ];
    }

    public function branchActive(int $schoolId, int $branchId): bool
    {
        return DB::table($this->branches())->where('id', $branchId)->where('school_id', $schoolId)->where('status', self::ACTIVE)->exists();
    }

    public function departmentOfBranch(int $branchId, int $departmentId): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'departments'))
            ->where('id', $departmentId)->where('branch_id', $branchId)->where('status', '<>', 3)->exists();
    }

    public function roomCodeTaken(int $branchId, string $code): bool
    {
        return DB::table($this->rooms())->where('branch_id', $branchId)->whereRaw('upper(code) = ?', [$code])->exists();
    }

    public function typeCodeTaken(int $schoolId, string $code, ?int $exceptTypeId = null): bool
    {
        return DB::table($this->typeTable())
            ->where(fn (Builder $q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId))
            ->whereRaw('upper(code) = ?', [$code])
            ->when($exceptTypeId !== null, fn (Builder $q) => $q->where('id', '<>', $exceptTypeId))
            ->exists();
    }

    public function roomUsage(int $roomId): int
    {
        return DB::table(SchemaHelper::qualified('timetable', 'schedules'))->where('room_id', $roomId)->where('lifecycle_status', 1)->count()
            + DB::table(SchemaHelper::qualified('timetable', 'activities'))->where('room_id', $roomId)->where('status', 1)->count()
            + DB::table(SchemaHelper::qualified('vocational', 'workshops'))->where('room_id', $roomId)->where('status', 1)->count();
    }

    public function typeUsage(int $schoolId, int $typeId): int
    {
        return DB::table($this->rooms().' as r')
            ->join($this->branches().' as b', 'b.id', '=', 'r.branch_id')
            ->where('b.school_id', $schoolId)->where('r.room_type_id', $typeId)->where('r.status', self::ACTIVE)
            ->count();
    }

    public function createRoom(int $branchId, string $code, RoomDetails $details, string $at): int
    {
        return (int) DB::table($this->rooms())->insertGetId([
            'branch_id' => $branchId,
            'code' => mb_strtoupper(trim($code)),
            'status' => self::ACTIVE,
            'created_at' => $at,
            'updated_at' => $at,
        ] + $details->toColumns());
    }

    public function updateRoom(int $roomId, RoomDetails $details, string $at): void
    {
        DB::table($this->rooms())->where('id', $roomId)->update(['updated_at' => $at] + $details->toColumns());
    }

    public function setRoomStatus(int $roomId, int $status, string $at): void
    {
        DB::table($this->rooms())->where('id', $roomId)->update(['status' => $status, 'updated_at' => $at]);
    }

    public function createType(int $schoolId, string $code, string $name, DisplayAppearance $appearance, int $kind, bool $practical, string $at): int
    {
        return (int) DB::table($this->typeTable())->insertGetId([
            'school_id' => $schoolId,
            'code' => mb_strtoupper(trim($code)),
            'name' => trim($name),
            'abbreviation' => $appearance->abbreviation,
            'color_hue' => $appearance->colorHue,
            'kind' => $kind,
            'supports_practical' => $practical,
            'sort_order' => 100,
            'status' => self::ACTIVE,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function updateType(int $typeId, string $name, DisplayAppearance $appearance, int $kind, bool $practical, string $at): void
    {
        DB::table($this->typeTable())->where('id', $typeId)->update([
            'name' => trim($name),
            'abbreviation' => $appearance->abbreviation,
            'color_hue' => $appearance->colorHue,
            'kind' => $kind,
            'supports_practical' => $practical,
            'updated_at' => $at,
        ]);
    }

    public function setTypeStatus(int $typeId, int $status, string $at): void
    {
        DB::table($this->typeTable())->where('id', $typeId)->update(['status' => $status, 'updated_at' => $at]);
    }

    /** @param  array{search?: string|null, branch_id?: int|null, room_type_id?: int|null, status?: int|null, practical?: bool|null}  $filters */
    private function filtered(int $schoolId, array $filters): Builder
    {
        $query = DB::table($this->rooms().' as r')
            ->join($this->branches().' as b', 'b.id', '=', 'r.branch_id')
            ->leftJoin($this->typeTable().' as t', 't.id', '=', 'r.room_type_id')
            ->leftJoin(SchemaHelper::qualified('organization', 'departments').' as d', 'd.id', '=', 'r.department_id')
            ->where('b.school_id', $schoolId);

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function (Builder $q) use ($like): void {
                foreach (['r.code', 'r.name', 'r.abbreviation', 'r.room_number', 'r.building', 'r.location', 'r.equipment', 't.name'] as $column) {
                    $q->orWhere($column, 'ilike', $like);
                }
            });
        }
        if (($filters['branch_id'] ?? null) !== null) {
            $query->where('r.branch_id', $filters['branch_id']);
        }
        if (($filters['room_type_id'] ?? null) !== null) {
            $query->where('r.room_type_id', $filters['room_type_id']);
        }
        if (($filters['status'] ?? null) !== null) {
            $query->where('r.status', $filters['status']);
        }
        if (($filters['practical'] ?? null) !== null) {
            $query->where('r.supports_practical', (bool) $filters['practical']);
        }

        return $query;
    }

    private function rooms(): string
    {
        return SchemaHelper::qualified('organization', 'rooms');
    }

    private function branches(): string
    {
        return SchemaHelper::qualified('organization', 'branches');
    }

    private function typeTable(): string
    {
        return SchemaHelper::qualified('organization', 'room_types');
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
