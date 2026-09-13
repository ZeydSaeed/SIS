<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Communication\ValueObjects\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComNotificationJobShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_notification_job(): void
    {
        $schoolId = $this->createSchool('SCH-COM-JS1', 'COM Show Job');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'show_job_tpl',
            'name' => 'Show Job Template',
            'channel' => NotificationChannel::Email,
            'body_template' => 'Hello',
        ], ['X-Idempotency-Key' => 'com-show-job-tpl'])->json('data.template_id');

        $jobId = (int) $this->postJson('/api/v1/communication/jobs', [
            'template_id' => $templateId,
            'target_filter' => ['recipient_type' => 'student', 'grade_level_id' => 1],
            'total_count' => 10,
        ], ['X-Idempotency-Key' => 'com-show-job'])->json('data.notification_job_id');

        $this->getJson('/api/v1/communication/jobs/'.$jobId)
            ->assertOk()
            ->assertJsonPath('data.id', $jobId)
            ->assertJsonPath('data.template_id', $templateId)
            ->assertJsonPath('data.total_count', 10)
            ->assertJsonPath('data.status', 1);
    }
}
