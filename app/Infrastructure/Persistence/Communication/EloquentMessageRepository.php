<?php

namespace App\Infrastructure\Persistence\Communication;

use App\Database\SchemaHelper;
use App\Domain\Communication\Data\MessageSnapshot;
use App\Domain\Communication\Repositories\MessageRepositoryInterface;
use App\Domain\Communication\ValueObjects\MessageStatus;
use Illuminate\Support\Facades\DB;

final class EloquentMessageRepository implements MessageRepositoryInterface
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
        ?int $templateId,
        string $recipientType,
        int $recipientId,
        int $channel,
        ?string $subject,
        string $body,
        int $status,
        string $idempotencyKey,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('communication', 'messages'))->insertGetId([
            'school_id' => $schoolId,
            'template_id' => $templateId,
            'recipient_type' => $recipientType,
            'recipient_id' => $recipientId,
            'channel' => $channel,
            'subject' => $subject,
            'body' => $body,
            'status' => $status,
            'sent_at' => null,
            'idempotency_key' => $idempotencyKey,
            'created_at' => $createdAt,
        ]);
    }

    public function findByIdForSchool(int $schoolId, int $messageId): ?MessageSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('communication', 'messages'))
            ->where('school_id', $schoolId)
            ->where('id', $messageId)
            ->first([
                'id',
                'school_id',
                'template_id',
                'recipient_type',
                'recipient_id',
                'channel',
                'subject',
                'body',
                'status',
                'sent_at',
                'idempotency_key',
                'created_at',
            ]);

        return $row !== null ? $this->toSnapshot($row) : null;
    }

    public function markSent(int $schoolId, int $messageId, int $status, string $sentAt): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('communication', 'messages'))
            ->where('school_id', $schoolId)
            ->where('id', $messageId)
            ->where('status', MessageStatus::Queued)
            ->update([
                'status' => $status,
                'sent_at' => $sentAt,
            ]) === 1;
    }

    public function cancelMessage(int $schoolId, int $messageId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('communication', 'messages'))
            ->where('school_id', $schoolId)
            ->where('id', $messageId)
            ->where('status', MessageStatus::Queued)
            ->update([
                'status' => MessageStatus::Failed,
            ]) === 1;
    }

    public function requeueMessage(int $schoolId, int $messageId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('communication', 'messages'))
            ->where('school_id', $schoolId)
            ->where('id', $messageId)
            ->where('status', MessageStatus::Failed)
            ->update([
                'status' => MessageStatus::Queued,
                'sent_at' => null,
            ]) === 1;
    }

    public function listBySchool(
        int $schoolId,
        ?string $recipientType = null,
        ?int $recipientId = null,
        ?int $status = null,
    ): array {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('communication', 'messages'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($recipientType !== null) {
            $q->where('recipient_type', $recipientType);
        }
        if ($recipientId !== null) {
            $q->where('recipient_id', $recipientId);
        }
        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get([
            'id',
            'school_id',
            'template_id',
            'recipient_type',
            'recipient_id',
            'channel',
            'subject',
            'body',
            'status',
            'sent_at',
            'idempotency_key',
            'created_at',
        ])->map(fn (object $row): MessageSnapshot => $this->toSnapshot($row))->all();
    }

    private function toSnapshot(object $row): MessageSnapshot
    {
        return new MessageSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            templateId: $row->template_id !== null ? (int) $row->template_id : null,
            recipientType: (string) $row->recipient_type,
            recipientId: (int) $row->recipient_id,
            channel: (int) $row->channel,
            subject: $row->subject !== null ? (string) $row->subject : null,
            body: (string) $row->body,
            status: (int) $row->status,
            sentAt: $row->sent_at !== null ? (string) $row->sent_at : null,
            idempotencyKey: (string) $row->idempotency_key,
            createdAt: (string) $row->created_at,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
