<?php

namespace App\Infrastructure\Persistence\Communication;

use App\Database\SchemaHelper;
use App\Domain\Communication\Data\NotificationTemplateSnapshot;
use App\Domain\Communication\Repositories\NotificationTemplateRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentNotificationTemplateRepository implements NotificationTemplateRepositoryInterface
{
    public function create(
        int $schoolId,
        string $code,
        string $name,
        int $channel,
        ?string $subjectTemplate,
        string $bodyTemplate,
        bool $isActive,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('communication', 'notification_templates'))->insertGetId([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'channel' => $channel,
            'subject_template' => $subjectTemplate,
            'body_template' => $bodyTemplate,
            'is_active' => $isActive,
            'created_at' => $createdAt,
        ]);
    }

    public function findIdBySchoolAndCode(int $schoolId, string $code): ?int
    {
        $this->bindSchool($schoolId);

        $id = DB::table(SchemaHelper::qualified('communication', 'notification_templates'))
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    public function listBySchool(int $schoolId, ?bool $activeOnly = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('communication', 'notification_templates'))
            ->where('school_id', $schoolId)
            ->orderBy('code');

        if ($activeOnly === true) {
            $q->where('is_active', true);
        }

        return $q->get([
            'id',
            'school_id',
            'code',
            'name',
            'channel',
            'subject_template',
            'body_template',
            'is_active',
            'created_at',
        ])->map(static function (object $row): NotificationTemplateSnapshot {
            return new NotificationTemplateSnapshot(
                id: (int) $row->id,
                schoolId: (int) $row->school_id,
                code: (string) $row->code,
                name: (string) $row->name,
                channel: (int) $row->channel,
                subjectTemplate: $row->subject_template !== null ? (string) $row->subject_template : null,
                bodyTemplate: (string) $row->body_template,
                isActive: (bool) $row->is_active,
                createdAt: (string) $row->created_at,
            );
        })->all();
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
