<?php

namespace App\Infrastructure\Persistence\Academic;

use App\Database\SchemaHelper;
use App\Domain\Academic\Data\HolidaySnapshot;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentHolidayRepository implements HolidayRepositoryInterface
{
    private const COLUMNS = [
        'id',
        'academic_year_id',
        'school_id',
        'name',
        'start_date',
        'end_date',
        'holiday_type',
        'created_at',
        'status',
    ];

    public function listVisible(int $schoolId, ?int $academicYearId = null, bool $includeInactive = false): array
    {
        $query = $this->visibleTo($schoolId)
            ->orderBy('start_date')
            ->orderBy('id');

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }
        if (! $includeInactive) {
            $query->where('status', self::ACTIVE);
        }

        return $query->get(self::COLUMNS)->map(fn (object $row): HolidaySnapshot => $this->snapshot($row))->all();
    }

    public function findVisible(int $schoolId, int $holidayId): ?HolidaySnapshot
    {
        $row = $this->visibleTo($schoolId)->where('id', $holidayId)->first(self::COLUMNS);

        return $row === null ? null : $this->snapshot($row);
    }

    public function overlapsActive(int $schoolId, int $academicYearId, string $name, string $startDate, string $endDate, ?int $exceptId): bool
    {
        $query = DB::table($this->table())
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', self::ACTIVE)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    public function create(int $schoolId, int $academicYearId, string $name, string $startDate, string $endDate, int $holidayType, string $at): int
    {
        return (int) DB::table($this->table())->insertGetId([
            'academic_year_id' => $academicYearId,
            'school_id' => $schoolId,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'holiday_type' => $holidayType,
            'status' => self::ACTIVE,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function update(int $holidayId, string $name, string $startDate, string $endDate, int $holidayType, string $at): void
    {
        DB::table($this->table())->where('id', $holidayId)->update([
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'holiday_type' => $holidayType,
            'updated_at' => $at,
        ]);
    }

    public function setStatus(int $holidayId, int $status, string $at): void
    {
        DB::table($this->table())->where('id', $holidayId)->update(['status' => $status, 'updated_at' => $at]);
    }

    private function table(): string
    {
        return SchemaHelper::qualified('academic', 'holidays');
    }

    /** Global holidays (school_id NULL) + the school's own. */
    private function visibleTo(int $schoolId): Builder
    {
        return DB::table($this->table())->where(function ($q) use ($schoolId): void {
            $q->whereNull('school_id')->orWhere('school_id', $schoolId);
        });
    }

    private function snapshot(object $row): HolidaySnapshot
    {
        return new HolidaySnapshot(
            id: (int) $row->id,
            academicYearId: (int) $row->academic_year_id,
            schoolId: $row->school_id !== null ? (int) $row->school_id : null,
            name: (string) $row->name,
            startDate: (string) $row->start_date,
            endDate: (string) $row->end_date,
            holidayType: (int) $row->holiday_type,
            createdAt: (string) $row->created_at,
            status: (int) $row->status,
        );
    }
}
