<?php

namespace App\Infrastructure\Persistence\Audit;

use App\Database\SchemaHelper;
use App\Domain\Audit\Data\AuditLogSnapshot;
use App\Domain\Audit\Repositories\AuditLogRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentAuditLogRepository implements AuditLogRepositoryInterface
{
    public function create(
        int $schoolId,
        ?int $userId,
        string $action,
        string $entityType,
        ?int $entityId,
        ?array $oldValues,
        ?array $newValues,
        ?string $ipAddress,
        ?string $userAgent,
        ?string $correlationId,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('audit', 'audit_logs'))->insertGetId([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => $oldValues === null ? null : json_encode($oldValues, JSON_THROW_ON_ERROR),
            'new_values' => $newValues === null ? null : json_encode($newValues, JSON_THROW_ON_ERROR),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'correlation_id' => $correlationId,
            'created_at' => $createdAt,
        ]);
    }

    public function listForSchool(
        int $schoolId,
        ?string $entityType,
        ?int $entityId,
        int $limit,
    ): array {
        $this->bindSchool($schoolId);

        $query = DB::table(SchemaHelper::qualified('audit', 'audit_logs'))
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->limit($limit);

        if ($entityType !== null && $entityType !== '') {
            $query->where('entity_type', $entityType);
        }
        if ($entityId !== null) {
            $query->where('entity_id', $entityId);
        }

        $rows = $query->get([
            'id', 'school_id', 'user_id', 'action', 'entity_type', 'entity_id',
            'old_values', 'new_values', 'ip_address', 'user_agent', 'correlation_id', 'created_at',
        ]);

        return $rows->map(fn ($row): AuditLogSnapshot => $this->map($row))->all();
    }

    public function findByIdForSchool(int $schoolId, int $auditLogId): ?AuditLogSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('audit', 'audit_logs'))
            ->where('id', $auditLogId)
            ->where('school_id', $schoolId)
            ->first([
                'id', 'school_id', 'user_id', 'action', 'entity_type', 'entity_id',
                'old_values', 'new_values', 'ip_address', 'user_agent', 'correlation_id', 'created_at',
            ]);

        return $row === null ? null : $this->map($row);
    }

    private function map(object $row): AuditLogSnapshot
    {
        return new AuditLogSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            userId: $row->user_id !== null ? (int) $row->user_id : null,
            action: (string) $row->action,
            entityType: (string) $row->entity_type,
            entityId: $row->entity_id !== null ? (int) $row->entity_id : null,
            oldValues: $this->decodeJson($row->old_values),
            newValues: $this->decodeJson($row->new_values),
            ipAddress: $row->ip_address !== null ? (string) $row->ip_address : null,
            userAgent: $row->user_agent !== null ? (string) $row->user_agent : null,
            correlationId: $row->correlation_id !== null ? (string) $row->correlation_id : null,
            createdAt: (string) $row->created_at,
        );
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
