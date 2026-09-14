<?php

namespace App\Infrastructure\Persistence\Academic;

use App\Database\SchemaHelper;
use App\Domain\Academic\Data\HolidaySnapshot;
use App\Domain\Academic\Repositories\HolidayRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentHolidayRepository implements HolidayRepositoryInterface
{
    public function listVisible(int $schoolId, ?int $academicYearId = null): array
    {
        $query = DB::table(SchemaHelper::qualified('academic', 'holidays'))
            ->where(function ($q) use ($schoolId): void {
                $q->whereNull('school_id')->orWhere('school_id', $schoolId);
            })
            ->orderBy('start_date')
            ->orderBy('id');

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query
            ->get([
                'id',
                'academic_year_id',
                'school_id',
                'name',
                'start_date',
                'end_date',
                'holiday_type',
                'created_at',
            ])
            ->map(static fn (object $row): HolidaySnapshot => new HolidaySnapshot(
                id: (int) $row->id,
                academicYearId: (int) $row->academic_year_id,
                schoolId: $row->school_id !== null ? (int) $row->school_id : null,
                name: (string) $row->name,
                startDate: (string) $row->start_date,
                endDate: (string) $row->end_date,
                holidayType: (int) $row->holiday_type,
                createdAt: (string) $row->created_at,
            ))
            ->all();
    }
}
