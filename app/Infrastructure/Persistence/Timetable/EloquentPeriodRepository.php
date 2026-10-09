<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Data\PersistPeriodData;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use App\Domain\Timetable\ValueObjects\PeriodPresentation;
use App\Domain\Timetable\ValueObjects\ScheduleLifecycleStatus;
use Illuminate\Support\Facades\DB;

final class EloquentPeriodRepository implements PeriodRepositoryInterface
{
    private const ACTIVE = 1;

    private const RETIRED = 2;

    private const COLUMNS = ['id', 'school_id', 'period_number', 'start_time', 'end_time', 'period_type', 'name', 'abbreviation', 'color_hue', 'show_in', 'print_in'];

    public function find(int $schoolId, int $periodId): ?PeriodSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('timetable', 'periods'))
            ->where('school_id', $schoolId)
            ->where('id', $periodId)
            ->where('status', self::ACTIVE)
            ->first(self::COLUMNS);

        if ($row === null) {
            return null;
        }

        return $this->map($row);
    }

    public function listForSchool(int $schoolId): array
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'periods'))
            ->where('school_id', $schoolId)
            ->where('status', self::ACTIVE)
            ->orderBy('period_number')
            ->orderBy('id')
            ->get(self::COLUMNS)
            ->map(fn (object $row): PeriodSnapshot => $this->map($row))
            ->all();
    }

    public function insert(PersistPeriodData $data): int
    {
        $this->bindSchool($data->schoolId);

        return (int) DB::table(SchemaHelper::qualified('timetable', 'periods'))->insertGetId($this->columns($data));
    }

    public function update(int $periodId, PersistPeriodData $data): void
    {
        $this->bindSchool($data->schoolId);

        DB::table(SchemaHelper::qualified('timetable', 'periods'))
            ->where('school_id', $data->schoolId)
            ->where('id', $periodId)
            ->update($this->columns($data));
    }

    public function replaceDay(int $schoolId, array $day): void
    {
        $this->bindSchool($schoolId);
        $table = SchemaHelper::qualified('timetable', 'periods');

        // Park the numbers out of the way first (UNIQUE(school_id, period_number) is checked row by row).
        DB::table($table)->where('school_id', $schoolId)->where('status', self::ACTIVE)->update(['period_number' => DB::raw('period_number + 100')]);

        foreach ($day as $row) {
            if ($row['id'] === null) {
                DB::table($table)->insert($this->columns($row['data']));
            } else {
                DB::table($table)->where('school_id', $schoolId)->where('id', $row['id'])->update($this->columns($row['data']));
            }
        }
    }

    public function retire(int $schoolId, int $periodId): void
    {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('timetable', 'periods'))
            ->where('school_id', $schoolId)->where('id', $periodId)
            ->update(['status' => self::RETIRED]);
    }

    public function hasActiveSchedules(int $schoolId, int $periodId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('timetable', 'schedules'))
            ->where('school_id', $schoolId)
            ->where('period_id', $periodId)
            ->where('lifecycle_status', ScheduleLifecycleStatus::Active->value)
            ->exists();
    }

    /** @return array<string, int|string|null> the presentation only when given (re-timing keeps titles and colours) */
    private function columns(PersistPeriodData $data): array
    {
        return [
            'school_id' => $data->schoolId,
            'period_number' => $data->periodNumber,
            'start_time' => $data->startTime,
            'end_time' => $data->endTime,
            'period_type' => $data->periodType,
        ] + ($data->presentation?->toColumns() ?? []);
    }

    private function map(object $row): PeriodSnapshot
    {
        return new PeriodSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            periodNumber: (int) $row->period_number,
            startTime: (string) $row->start_time,
            endTime: (string) $row->end_time,
            periodType: (int) $row->period_type,
            presentation: new PeriodPresentation(
                name: $row->name !== null ? (string) $row->name : null,
                abbreviation: $row->abbreviation !== null ? (string) $row->abbreviation : null,
                colorHue: $row->color_hue !== null ? (int) $row->color_hue : null,
                showIn: (int) $row->show_in,
                printIn: (int) $row->print_in,
            ),
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
