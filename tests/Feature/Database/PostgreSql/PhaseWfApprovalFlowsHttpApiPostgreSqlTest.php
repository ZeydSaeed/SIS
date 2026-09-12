<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfApprovalFlowsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function approval_flows_table_has_force_rls_and_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'workflow' AND table_name = 'approval_flows'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);

        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'workflow' AND c.relname = 'approval_flows'
        ");
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function workflow_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::WORKFLOW_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::WORKFLOW_MANAGE, config('security.permissions'));
    }

    #[Test]
    public function manager_can_create_and_list_approval_flows(): void
    {
        $schoolId = $this->createSchool('SCH-WF-1', 'Workflow 1');
        $this->actingAsWorkflowManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/workflow/approval-flows', [
            'entity_type' => 'transfer_request',
            'name' => 'Transfer dual approval',
            'steps' => [
                ['step' => 1, 'role' => 'transfers_manager'],
                ['step' => 2, 'role' => 'school_admin'],
            ],
        ], ['X-Idempotency-Key' => 'wf-flow-1'])
            ->assertCreated();

        $flowId = (int) $create->json('data.flow_id');

        $this->postJson('/api/v1/workflow/approval-flows', [
            'entity_type' => 'transfer_request',
            'name' => 'Transfer dual approval',
            'steps' => [
                ['step' => 1, 'role' => 'transfers_manager'],
                ['step' => 2, 'role' => 'school_admin'],
            ],
        ], ['X-Idempotency-Key' => 'wf-flow-1'])
            ->assertOk()
            ->assertJsonPath('data.flow_id', $flowId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/workflow/approval-flows?entity_type=transfer_request')
            ->assertOk()
            ->assertJsonPath('data.0.id', $flowId)
            ->assertJsonPath('data.0.entity_type', 'transfer_request')
            ->assertJsonPath('data.0.steps.0.role', 'transfers_manager');

        $this->assertDatabaseHas(SchemaHelper::qualified('workflow', 'approval_flows'), [
            'id' => $flowId,
            'school_id' => $schoolId,
            'entity_type' => 'transfer_request',
        ]);
    }

    #[Test]
    public function viewer_cannot_create_approval_flow(): void
    {
        $schoolId = $this->createSchool('SCH-WF-2', 'Workflow 2');
        $this->actingAsWorkflowViewerForSchool($schoolId);

        $this->postJson('/api/v1/workflow/approval-flows', [
            'entity_type' => 'fee_type',
            'name' => 'Fee change',
            'steps' => [
                ['step' => 1, 'role' => 'finance_manager'],
            ],
        ], ['X-Idempotency-Key' => 'wf-deny'])
            ->assertForbidden();
    }
}
