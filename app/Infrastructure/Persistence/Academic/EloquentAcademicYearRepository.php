<?php

namespace App\Infrastructure\Persistence\Academic;

use App\Database\SchemaHelper;
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
}
