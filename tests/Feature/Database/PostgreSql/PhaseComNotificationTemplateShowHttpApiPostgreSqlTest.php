<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Communication\ValueObjects\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComNotificationTemplateShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_notification_template(): void
    {
        $schoolId = $this->createSchool('SCH-COM-S1', 'COM Show Template');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'show_tpl',
            'name' => 'Show Template',
            'channel' => NotificationChannel::Email,
            'subject_template' => 'Show subject',
            'body_template' => 'Show body',
        ], ['X-Idempotency-Key' => 'com-show-tpl'])->json('data.template_id');

        $this->getJson('/api/v1/communication/templates/'.$templateId)
            ->assertOk()
            ->assertJsonPath('data.id', $templateId)
            ->assertJsonPath('data.code', 'SHOW_TPL')
            ->assertJsonPath('data.name', 'Show Template')
            ->assertJsonPath('data.channel', NotificationChannel::Email);
    }
}
