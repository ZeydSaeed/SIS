<?php

namespace App\Infrastructure\Persistence\Communication;

use App\Database\SchemaHelper;
use App\Domain\Communication\Data\NotificationJobSnapshot;
use App\Domain\Communication\Repositories\NotificationJobRepositoryInterface;
use App\Domain\Communication\ValueObjects\NotificationJobStatus;
use Illuminate\Support\Facades\DB;

final class EloquentNotificationJobRepository implements NotificationJobRepositoryInterface
{
    public function templateBelongsToSchool(int $schoolId, int $templateId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('communication', 'notification_templates'))
            ->where('school_id', $schoolId)
            ->where('id', $templateId)
            ->exists();
    }

    public function create(
        int $schoolId,
        int $templateId,
        array $targetFilter,
        int $totalCount,
        int $status,
        string $idempotencyKey,
        ?int $createdBy,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('communication', 'notification_jobs'))->insertGetId([
            'school_id' => $schoolId,
            'template_id' => $templateId,
            'target_filter' => json_encode($targetFilter, JSON_THROW_ON_ERROR),
            'total_count' => $totalCount,
            'sent_count' => 0,
            'status' => $status,
            'idempotency_key' => $idempotencyKey,
            'created_by' => $createdBy,
            'created_at' => $createdAt,
            'completed_at' => null,
        ]);
    }

    public function findByIdForSchool(int $schoolId, int $jobId): ?NotificationJobSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('communication', 'notification_jobs'))
            ->where('school_id', $schoolId)
            ->where('id', $jobId)
            ->first([
                'id', 'school_id', 'job_id', 'template_id', 'target_filter',
                'total_count', 'sent_count', 'status', 'created_by', 'created_at', 'completed_at',
            ]);

        return $row === null ? null : $this->map($row);
    }

    public function listForSchool(int $schoolId, ?int $status, int $limit): array
    {
        $this->bindSchool($schoolId);

        $query = DB::table(SchemaHelper::qualified('communication', 'notification_jobs'))
            ->where('school_id', $schoolId)
            ->orderByDesc('id')
            ->limit($limit);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get([
            'id', 'school_id', 'job_id', 'template_id', 'target_filter',
            'total_count', 'sent_count', 'status', 'created_by', 'created_at', 'completed_at',
        ])->map(fn ($row): NotificationJobSnapshot => $this->map($row))->all();
    }

    public function markCompleted(int $schoolId, int $id, int $sentCount, string $completedAt): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('communication', 'notification_jobs'))
            ->where('school_id', $schoolId)
            ->where('id', $id)
            ->where('status', NotificationJobStatus::Open)
            ->where('total_count', '>=', $sentCount)
            ->update([
                'status' => NotificationJobStatus::Completed,
                'sent_count' => $sentCount,
                'completed_at' => $completedAt,
            ]) > 0;
    }

    public function markCancelled(int $schoolId, int $id, string $completedAt): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('communication', 'notification_jobs'))
            ->where('school_id', $schoolId)
            ->where('id', $id)
            ->where('status', NotificationJobStatus::Open)
            ->update([
                'status' => NotificationJobStatus::Cancelled,
                'completed_at' => $completedAt,
            ]) > 0;
    }

    public function markReopened(int $schoolId, int $id): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('communication', 'notification_jobs'))
            ->where('school_id', $schoolId)
            ->where('id', $id)
            ->where('status', NotificationJobStatus::Cancelled)
            ->update([
                'status' => NotificationJobStatus::Open,
                'completed_at' => null,
            ]) > 0;
    }

    private function map(object $row): NotificationJobSnapshot
    {
        $filter = $row->target_filter;
        if (is_string($filter)) {
            $decoded = json_decode($filter, true);
            $filter = is_array($decoded) ? $decoded : [];
        } elseif (! is_array($filter)) {
            $filter = [];
        }

        return new NotificationJobSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            jobId: (string) $row->job_id,
            templateId: (int) $row->template_id,
            targetFilter: $filter,
            totalCount: (int) $row->total_count,
            sentCount: (int) $row->sent_count,
            status: (int) $row->status,
            createdBy: $row->created_by !== null ? (int) $row->created_by : null,
            createdAt: (string) $row->created_at,
            completedAt: $row->completed_at !== null ? (string) $row->completed_at : null,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
