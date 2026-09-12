<?php

namespace App\Infrastructure\Persistence\Workflow;

use App\Database\SchemaHelper;
use App\Domain\Workflow\Data\ApprovalRequestSnapshot;
use App\Domain\Workflow\Repositories\ApprovalRequestRepositoryInterface;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Illuminate\Support\Facades\DB;

final class EloquentApprovalRequestRepository implements ApprovalRequestRepositoryInterface
{
    public function create(
        int $schoolId,
        int $flowId,
        string $entityType,
        int $entityId,
        int $currentStep,
        int $status,
        ?int $requestedBy,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))->insertGetId([
            'school_id' => $schoolId,
            'flow_id' => $flowId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'current_step' => $currentStep,
            'status' => $status,
            'requested_by' => $requestedBy,
            'created_at' => $createdAt,
            'completed_at' => null,
        ]);
    }

    public function hasOpenForEntity(int $schoolId, string $entityType, int $entityId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))
            ->where('school_id', $schoolId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', ApprovalRequestStatus::Pending)
            ->exists();
    }

    public function findById(int $schoolId, int $requestId): ?ApprovalRequestSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))
            ->where('school_id', $schoolId)
            ->where('id', $requestId)
            ->first([
                'id',
                'school_id',
                'flow_id',
                'entity_type',
                'entity_id',
                'current_step',
                'status',
                'requested_by',
                'created_at',
                'completed_at',
            ]);

        return $row === null ? null : $this->mapRow($row);
    }

    public function applyDecision(
        int $schoolId,
        int $requestId,
        int $status,
        int $currentStep,
        ?string $completedAt,
    ): void {
        $this->bindSchool($schoolId);

        DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))
            ->where('school_id', $schoolId)
            ->where('id', $requestId)
            ->update([
                'status' => $status,
                'current_step' => $currentStep,
                'completed_at' => $completedAt,
            ]);
    }

    public function listBySchool(
        int $schoolId,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $status = null,
    ): array {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($entityType !== null) {
            $q->where('entity_type', $entityType);
        }
        if ($entityId !== null) {
            $q->where('entity_id', $entityId);
        }
        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get([
            'id',
            'school_id',
            'flow_id',
            'entity_type',
            'entity_id',
            'current_step',
            'status',
            'requested_by',
            'created_at',
            'completed_at',
        ])->map(fn (object $row): ApprovalRequestSnapshot => $this->mapRow($row))->all();
    }

    private function mapRow(object $row): ApprovalRequestSnapshot
    {
        return new ApprovalRequestSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            flowId: (int) $row->flow_id,
            entityType: (string) $row->entity_type,
            entityId: (int) $row->entity_id,
            currentStep: (int) $row->current_step,
            status: (int) $row->status,
            requestedBy: $row->requested_by !== null ? (int) $row->requested_by : null,
            createdAt: (string) $row->created_at,
            completedAt: $row->completed_at !== null ? (string) $row->completed_at : null,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
