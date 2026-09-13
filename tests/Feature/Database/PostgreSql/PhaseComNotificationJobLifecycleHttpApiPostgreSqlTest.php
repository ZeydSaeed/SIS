<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComNotificationJobLifecycleHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_complete_notification_job(): void
    {
        $schoolId = $this->createSchool('SCH-JOB-LC1', 'Jobs Lifecycle 1');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'JOB-LC1',
            'name' => 'LC Template',
            'channel' => NotificationChannel::Email,
            'body_template' => 'Body',
        ], ['X-Idempotency-Key' => 'job-lc-tpl-1'])
            ->json('data.template_id');

        $jobId = (int) $this->postJson('/api/v1/communication/jobs', [
            'template_id' => $templateId,
            'target_filter' => ['grade' => 1],
            'total_count' => 5,
        ], ['X-Idempotency-Key' => 'job-lc-create-1'])
            ->json('data.notification_job_id');

        $this->postJson('/api/v1/communication/jobs/'.$jobId.'/complete', [
            'sent_count' => 4,
        ], ['X-Idempotency-Key' => 'job-lc-complete-1'])
            ->assertOk()
            ->assertJsonPath('data.status', 2);

        $this->postJson('/api/v1/communication/jobs/'.$jobId.'/complete', [
            'sent_count' => 4,
        ], ['X-Idempotency-Key' => 'job-lc-complete-1'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->assertDatabaseHas(SchemaHelper::qualified('communication', 'notification_jobs'), [
            'id' => $jobId,
            'status' => 2,
            'sent_count' => 4,
        ]);
    }

    #[Test]
    public function manager_can_cancel_open_notification_job(): void
    {
        $schoolId = $this->createSchool('SCH-JOB-LC2', 'Jobs Lifecycle 2');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'JOB-LC2',
            'name' => 'LC Template 2',
            'channel' => NotificationChannel::Email,
            'body_template' => 'Body',
        ], ['X-Idempotency-Key' => 'job-lc-tpl-2'])
            ->json('data.template_id');

        $jobId = (int) $this->postJson('/api/v1/communication/jobs', [
            'template_id' => $templateId,
            'target_filter' => ['all' => true],
            'total_count' => 3,
        ], ['X-Idempotency-Key' => 'job-lc-create-2'])
            ->json('data.notification_job_id');

        $this->postJson('/api/v1/communication/jobs/'.$jobId.'/cancel', [], [
            'X-Idempotency-Key' => 'job-lc-cancel-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 3);

        $this->assertDatabaseHas(SchemaHelper::qualified('communication', 'notification_jobs'), [
            'id' => $jobId,
            'status' => 3,
        ]);
    }

    #[Test]
    public function rejects_complete_when_sent_count_exceeds_total(): void
    {
        $schoolId = $this->createSchool('SCH-JOB-LC3', 'Jobs Lifecycle 3');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'JOB-LC3',
            'name' => 'LC Template 3',
            'channel' => NotificationChannel::Email,
            'body_template' => 'Body',
        ], ['X-Idempotency-Key' => 'job-lc-tpl-3'])
            ->json('data.template_id');

        $jobId = (int) $this->postJson('/api/v1/communication/jobs', [
            'template_id' => $templateId,
            'target_filter' => ['x' => 1],
            'total_count' => 2,
        ], ['X-Idempotency-Key' => 'job-lc-create-3'])
            ->json('data.notification_job_id');

        $this->postJson('/api/v1/communication/jobs/'.$jobId.'/complete', [
            'sent_count' => 9,
        ], ['X-Idempotency-Key' => 'job-lc-bad'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'communication.job_sent_count_invalid');
    }
}
