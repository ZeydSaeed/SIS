<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComNotificationTemplateReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_notification_template(): void
    {
        $schoolId = $this->createSchool('SCH-COM-R1', 'Template Reactivate');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'tpl_on',
            'name' => 'Template On',
            'channel' => NotificationChannel::Sms,
            'body_template' => 'body',
            'is_active' => true,
        ], ['X-Idempotency-Key' => 'com-tpl-r-create'])->json('data.template_id');

        $this->postJson('/api/v1/communication/templates/'.$templateId.'/deactivate', [], [
            'X-Idempotency-Key' => 'com-tpl-r-off',
        ])->assertOk();

        $this->postJson('/api/v1/communication/templates/'.$templateId.'/reactivate', [], [
            'X-Idempotency-Key' => 'com-tpl-r-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas(SchemaHelper::qualified('communication', 'notification_templates'), [
            'id' => $templateId,
            'is_active' => true,
        ]);
    }
}
