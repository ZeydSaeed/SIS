<?php

namespace App\Infrastructure\Persistence\Academic;

use App\Database\SchemaHelper;
use App\Domain\Academic\Data\AcademicYearSnapshot;
use App\Domain\Academic\Repositories\AcademicYearRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentAcademicYearRepository implements AcademicYearRepositoryInterface
{
    public function findIdByCode(string $code): ?int
    {
        $id = DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('code', $code)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function findById(int $id): ?AcademicYearSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('id', $id)
            ->first([
                'id',
                'code',
                'name',
                'start_date',
                'end_date',
                'is_current',
                'status',
                'created_at',
                'updated_at',
            ]);

        return $row === null ? null : $this->map($row);
    }

    public function listAll(): array
    {
        return DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->orderByDesc('start_date')
            ->orderBy('id')
            ->get([
                'id',
                'code',
                'name',
                'start_date',
                'end_date',
                'is_current',
                'status',
                'created_at',
                'updated_at',
            ])
            ->map(fn (object $row): AcademicYearSnapshot => $this->map($row))
            ->all();
    }

    public function insert(
        string $code,
        string $name,
        string $startDate,
        string $endDate,
        bool $isCurrent,
        int $status,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('academic', 'academic_years'))->insertGetId([
            'code' => $code,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_current' => $isCurrent,
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function map(object $row): AcademicYearSnapshot
    {
        return new AcademicYearSnapshot(
            id: (int) $row->id,
            code: (string) $row->code,
            name: (string) $row->name,
            startDate: (string) $row->start_date,
            endDate: (string) $row->end_date,
            isCurrent: (bool) $row->is_current,
            status: (int) $row->status,
            createdAt: (string) $row->created_at,
            updatedAt: (string) $row->updated_at,
        );
    }
}
