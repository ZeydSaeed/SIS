<?php

namespace App\Infrastructure\Persistence\Workflow;

use App\Database\SchemaHelper;
use App\Domain\Workflow\Data\ApprovalFlowSnapshot;
use App\Domain\Workflow\Repositories\ApprovalFlowRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentApprovalFlowRepository implements ApprovalFlowRepositoryInterface
{
    public function create(
        int $schoolId,
        string $entityType,
        string $name,
        array $steps,
        bool $isActive,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        $table = SchemaHelper::qualified('workflow', 'approval_flows');
        $row = DB::selectOne(
            "INSERT INTO {$table} (school_id, entity_type, name, steps, is_active, created_at)
             VALUES (?, ?, ?, ?::jsonb, ?, ?)
             RETURNING id",
            [
                $schoolId,
                $entityType,
                $name,
                json_encode($steps, JSON_THROW_ON_ERROR),
                $isActive,
                $createdAt,
            ],
        );

        return (int) $row->id;
    }

    public function listBySchool(int $schoolId, ?string $entityType = null, ?bool $activeOnly = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('workflow', 'approval_flows'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($entityType !== null) {
            $q->where('entity_type', $entityType);
        }

        if ($activeOnly === true) {
            $q->where('is_active', true);
        }

        return $q->get([
            'id',
            'school_id',
            'entity_type',
            'name',
            'steps',
            'is_active',
            'created_at',
        ])->map(fn (object $row): ApprovalFlowSnapshot => $this->mapRow($row))->all();
    }

    public function findById(int $schoolId, int $flowId): ?ApprovalFlowSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('workflow', 'approval_flows'))
            ->where('school_id', $schoolId)
            ->where('id', $flowId)
            ->first([
                'id',
                'school_id',
                'entity_type',
                'name',
                'steps',
                'is_active',
                'created_at',
            ]);

        return $row === null ? null : $this->mapRow($row);
    }

    public function findActiveByEntityType(int $schoolId, string $entityType): ?ApprovalFlowSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('workflow', 'approval_flows'))
            ->where('school_id', $schoolId)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->orderBy('id')
            ->first([
                'id',
                'school_id',
                'entity_type',
                'name',
                'steps',
                'is_active',
                'created_at',
            ]);

        return $row === null ? null : $this->mapRow($row);
    }

    private function mapRow(object $row): ApprovalFlowSnapshot
    {
        $decoded = is_string($row->steps)
            ? json_decode($row->steps, true, 512, JSON_THROW_ON_ERROR)
            : (array) $row->steps;

        /** @var list<array{step:int, role:string}> $steps */
        $steps = array_map(static function (mixed $item): array {
            $arr = (array) $item;

            return [
                'step' => (int) $arr['step'],
                'role' => (string) $arr['role'],
            ];
        }, $decoded);

        return new ApprovalFlowSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            entityType: (string) $row->entity_type,
            name: (string) $row->name,
            steps: $steps,
            isActive: (bool) $row->is_active,
            createdAt: (string) $row->created_at,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
