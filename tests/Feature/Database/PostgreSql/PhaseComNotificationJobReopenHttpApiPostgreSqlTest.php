<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use App\Domain\Communication\ValueObjects\NotificationJobStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComNotificationJobReopenHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reopen_cancelled_notification_job(): void
    {
        $schoolId = $this->createSchool('SCH-COM-JR', 'Job Reopen');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'JOB-REOP',
            'name' => 'Reopen Template',
            'channel' => NotificationChannel::Email,
            'body_template' => 'Body',
        ], ['X-Idempotency-Key' => 'com-jr-tpl'])->json('data.template_id');

        $jobId = (int) $this->postJson('/api/v1/communication/jobs', [
            'template_id' => $templateId,
            'target_filter' => ['all' => true],
            'total_count' => 2,
        ], ['X-Idempotency-Key' => 'com-jr-create'])->json('data.notification_job_id');

        $this->postJson('/api/v1/communication/jobs/'.$jobId.'/cancel', [], [
            'X-Idempotency-Key' => 'com-jr-cancel',
        ])->assertOk();

        $this->postJson('/api/v1/communication/jobs/'.$jobId.'/reopen', [], [
            'X-Idempotency-Key' => 'com-jr-reopen',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', NotificationJobStatus::Open);

        $this->assertDatabaseHas(SchemaHelper::qualified('communication', 'notification_jobs'), [
            'id' => $jobId,
            'status' => NotificationJobStatus::Open,
        ]);
    }
}
