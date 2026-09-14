<?php

namespace App\Infrastructure\Persistence\Timetable;

use App\Database\SchemaHelper;
use App\Domain\Timetable\Data\PeriodSnapshot;
use App\Domain\Timetable\Repositories\PeriodRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentPeriodRepository implements PeriodRepositoryInterface
{
    public function find(int $schoolId, int $periodId): ?PeriodSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('timetable', 'periods'))
            ->where('school_id', $schoolId)
            ->where('id', $periodId)
            ->first([
                'id',
                'school_id',
                'period_number',
                'start_time',
                'end_time',
                'period_type',
            ]);

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
            ->orderBy('period_number')
            ->orderBy('id')
            ->get([
                'id',
                'school_id',
                'period_number',
                'start_time',
                'end_time',
                'period_type',
            ])
            ->map(fn (object $row): PeriodSnapshot => $this->map($row))
            ->all();
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
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
