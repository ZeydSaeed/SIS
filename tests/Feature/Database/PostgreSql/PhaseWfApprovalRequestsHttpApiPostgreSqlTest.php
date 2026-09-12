<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfApprovalRequestsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,flow_id:int}
     */
    private function seedFlow(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-WFR-'.$suffix, 'WF Req '.$suffix);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $table = SchemaHelper::qualified('workflow', 'approval_flows');
        $row = DB::selectOne(
            "INSERT INTO {$table} (school_id, entity_type, name, steps, is_active, created_at)
             VALUES (?, ?, ?, ?::jsonb, true, NOW())
             RETURNING id",
            [
                $schoolId,
                'transfer_request',
                'Transfer flow '.$suffix,
                json_encode([['step' => 1, 'role' => 'transfers_manager']], JSON_THROW_ON_ERROR),
            ],
        );

        return [
            'school_id' => $schoolId,
            'flow_id' => (int) $row->id,
        ];
    }

    #[Test]
    public function approval_requests_table_has_force_rls_and_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'workflow' AND table_name = 'approval_requests'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);

        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'workflow' AND c.relname = 'approval_requests'
        ");
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function manager_can_create_and_list_approval_requests(): void
    {
        $ctx = $this->seedFlow('R1');
        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);

        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 101,
        ], ['X-Idempotency-Key' => 'wfr-1'])
            ->assertCreated();

        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 101,
        ], ['X-Idempotency-Key' => 'wfr-1'])
            ->assertOk()
            ->assertJsonPath('data.request_id', $requestId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/workflow/approval-requests?entity_type=transfer_request&request_status=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $requestId)
            ->assertJsonPath('data.0.status', ApprovalRequestStatus::Pending)
            ->assertJsonPath('data.0.current_step', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('workflow', 'approval_requests'), [
            'id' => $requestId,
            'school_id' => $ctx['school_id'],
            'entity_id' => 101,
        ]);
    }

    #[Test]
    public function duplicate_open_request_is_rejected(): void
    {
        $ctx = $this->seedFlow('R2');
        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 202,
        ], ['X-Idempotency-Key' => 'wfr-dup-1'])
            ->assertCreated();

        $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 202,
        ], ['X-Idempotency-Key' => 'wfr-dup-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'workflow.approval_request_open_exists');
    }

    #[Test]
    public function viewer_cannot_create_approval_request(): void
    {
        $ctx = $this->seedFlow('R3');
        $this->actingAsWorkflowViewerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 1,
        ], ['X-Idempotency-Key' => 'wfr-deny'])
            ->assertForbidden();
    }
}
