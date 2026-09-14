<?php

namespace App\Infrastructure\Persistence\Academic;

use App\Database\SchemaHelper;
use App\Domain\Academic\Data\GradeLevelSnapshot;
use App\Domain\Academic\Repositories\GradeLevelRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentGradeLevelRepository implements GradeLevelRepositoryInterface
{
    public function findById(int $id): ?GradeLevelSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->where('id', $id)
            ->first([
                'id',
                'code',
                'name',
                'level_order',
                'education_stage',
                'status',
            ]);

        return $row === null ? null : $this->map($row);
    }

    public function listAll(): array
    {
        return DB::table(SchemaHelper::qualified('academic', 'grade_levels'))
            ->orderBy('level_order')
            ->orderBy('id')
            ->get([
                'id',
                'code',
                'name',
                'level_order',
                'education_stage',
                'status',
            ])
            ->map(fn (object $row): GradeLevelSnapshot => $this->map($row))
            ->all();
    }

    private function map(object $row): GradeLevelSnapshot
    {
        return new GradeLevelSnapshot(
            id: (int) $row->id,
            code: (string) $row->code,
            name: (string) $row->name,
            levelOrder: (int) $row->level_order,
            educationStage: (int) $row->education_stage,
            status: (int) $row->status,
        );
    }
}
