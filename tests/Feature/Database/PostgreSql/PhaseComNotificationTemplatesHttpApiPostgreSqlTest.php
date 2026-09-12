<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComNotificationTemplatesHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function notification_templates_table_has_force_rls_and_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'communication' AND table_name = 'notification_templates'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);

        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'communication' AND c.relname = 'notification_templates'
        ");
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function communication_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::COMMUNICATION_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::COMMUNICATION_MANAGE, config('security.permissions'));
    }

    #[Test]
    public function manager_can_create_and_list_templates(): void
    {
        $schoolId = $this->createSchool('SCH-COM-1', 'Communication 1');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/communication/templates', [
            'code' => 'absent_alert',
            'name' => 'Absence alert',
            'channel' => NotificationChannel::Email,
            'subject_template' => 'Absence notice',
            'body_template' => 'Student was absent.',
        ], ['X-Idempotency-Key' => 'com-tpl-1'])
            ->assertCreated();

        $templateId = (int) $create->json('data.template_id');

        $this->postJson('/api/v1/communication/templates', [
            'code' => 'absent_alert',
            'name' => 'Absence alert',
            'channel' => NotificationChannel::Email,
            'subject_template' => 'Absence notice',
            'body_template' => 'Student was absent.',
        ], ['X-Idempotency-Key' => 'com-tpl-1'])
            ->assertOk()
            ->assertJsonPath('data.template_id', $templateId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/communication/templates')
            ->assertOk()
            ->assertJsonPath('data.0.id', $templateId)
            ->assertJsonPath('data.0.code', 'ABSENT_ALERT')
            ->assertJsonPath('data.0.channel', NotificationChannel::Email);

        $this->assertDatabaseHas(SchemaHelper::qualified('communication', 'notification_templates'), [
            'id' => $templateId,
            'school_id' => $schoolId,
            'code' => 'ABSENT_ALERT',
        ]);
    }

    #[Test]
    public function viewer_cannot_create_template(): void
    {
        $schoolId = $this->createSchool('SCH-COM-2', 'Communication 2');
        $this->actingAsCommunicationViewerForSchool($schoolId);

        $this->postJson('/api/v1/communication/templates', [
            'code' => 'x',
            'name' => 'X',
            'channel' => NotificationChannel::Sms,
            'body_template' => 'hi',
        ], ['X-Idempotency-Key' => 'com-deny'])
            ->assertForbidden();
    }
}
