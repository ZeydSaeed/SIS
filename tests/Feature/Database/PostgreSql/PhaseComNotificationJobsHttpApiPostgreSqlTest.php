<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Communication\ValueObjects\NotificationChannel;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseComNotificationJobsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function notification_jobs_have_force_rls(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'communication' AND c.relname = 'notification_jobs'
        ");
        $this->assertTrue((bool) $row->rls);
        $this->assertTrue((bool) $row->force_rls);
    }

    #[Test]
    public function manager_can_create_and_list_notification_job(): void
    {
        $schoolId = $this->createSchool('SCH-JOB-1', 'Jobs 1');
        $this->actingAsCommunicationManagerForSchool($schoolId);

        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'JOB-T1',
            'name' => 'Job Template',
            'channel' => NotificationChannel::Email,
            'body_template' => 'Hello',
        ], ['X-Idempotency-Key' => 'job-tpl-1'])
            ->assertCreated()
            ->json('data.template_id');

        $jobId = (int) $this->postJson('/api/v1/communication/jobs', [
            'template_id' => $templateId,
            'target_filter' => ['recipient_type' => 'student', 'grade_level_id' => 1],
            'total_count' => 10,
        ], ['X-Idempotency-Key' => 'job-create-1'])
            ->assertCreated()
            ->json('data.notification_job_id');

        $this->postJson('/api/v1/communication/jobs', [
            'template_id' => $templateId,
            'target_filter' => ['recipient_type' => 'student', 'grade_level_id' => 1],
            'total_count' => 10,
        ], ['X-Idempotency-Key' => 'job-create-1'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/communication/jobs?job_status=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $jobId)
            ->assertJsonPath('data.0.status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('communication', 'notification_jobs'), [
            'id' => $jobId,
            'school_id' => $schoolId,
            'template_id' => $templateId,
            'total_count' => 10,
        ]);
    }

    #[Test]
    public function viewer_cannot_create_notification_job(): void
    {
        $schoolId = $this->createSchool('SCH-JOB-2', 'Jobs 2');
        $this->actingAsCommunicationManagerForSchool($schoolId);
        $templateId = (int) $this->postJson('/api/v1/communication/templates', [
            'code' => 'JOB-T2',
            'name' => 'Job Template 2',
            'channel' => NotificationChannel::Email,
            'body_template' => 'Hi',
        ], ['X-Idempotency-Key' => 'job-tpl-2'])
            ->json('data.template_id');

        $this->actingAsCommunicationViewerForSchool($schoolId);

        $this->postJson('/api/v1/communication/jobs', [
            'template_id' => $templateId,
            'target_filter' => ['all' => true],
            'total_count' => 1,
        ], ['X-Idempotency-Key' => 'job-deny'])
            ->assertForbidden();
    }
}
