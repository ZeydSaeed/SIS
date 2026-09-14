<?php

namespace App\Infrastructure\Persistence\Academic;

use App\Database\SchemaHelper;
use App\Domain\Academic\Data\TermSnapshot;
use App\Domain\Academic\Repositories\TermRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentTermRepository implements TermRepositoryInterface
{
    public function listAll(?int $academicYearId = null): array
    {
        $query = DB::table(SchemaHelper::qualified('academic', 'terms'))
            ->orderBy('academic_year_id')
            ->orderBy('term_order')
            ->orderBy('id');

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        return $query
            ->get([
                'id',
                'academic_year_id',
                'code',
                'name',
                'start_date',
                'end_date',
                'term_order',
                'status',
                'created_at',
                'updated_at',
            ])
            ->map(fn (object $row): TermSnapshot => $this->map($row))
            ->all();
    }

    public function findById(int $id): ?TermSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('academic', 'terms'))
            ->where('id', $id)
            ->first([
                'id',
                'academic_year_id',
                'code',
                'name',
                'start_date',
                'end_date',
                'term_order',
                'status',
                'created_at',
                'updated_at',
            ]);

        return $row === null ? null : $this->map($row);
    }

    private function map(object $row): TermSnapshot
    {
        return new TermSnapshot(
            id: (int) $row->id,
            academicYearId: (int) $row->academic_year_id,
            code: (string) $row->code,
            name: (string) $row->name,
            startDate: (string) $row->start_date,
            endDate: (string) $row->end_date,
            termOrder: (int) $row->term_order,
            status: (int) $row->status,
            createdAt: (string) $row->created_at,
            updatedAt: (string) $row->updated_at,
        );
    }
}
