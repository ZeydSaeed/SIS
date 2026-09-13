<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComNotificationTemplateDeactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_deactivate_notification_template(): void
    {
        $schoolId = $this->createSchool('SCH-COM-D1', 'Template Deactivate');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'tpl_off',
            'name' => 'Template Off',
            'channel' => NotificationChannel::Email,
            'body_template' => 'body',
            'is_active' => true,
        ], ['X-Idempotency-Key' => 'com-tpl-d-create'])->json('data.template_id');

        $this->postJson('/api/v1/communication/templates/'.$templateId.'/deactivate', [], [
            'X-Idempotency-Key' => 'com-tpl-d-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas(SchemaHelper::qualified('communication', 'notification_templates'), [
            'id' => $templateId,
            'is_active' => false,
        ]);
    }
}
