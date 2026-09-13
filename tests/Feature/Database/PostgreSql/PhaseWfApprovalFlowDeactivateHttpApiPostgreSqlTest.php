<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfApprovalFlowDeactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_deactivate_approval_flow(): void
    {
        $schoolId = $this->createSchool('SCH-WF-D1', 'Flow Deactivate');
        $this->actingAsWorkflowManagerForSchool($schoolId);

        $flowId = (int) $this->postJson('/api/v1/workflow/approval-flows', [
            'entity_type' => 'transfer_request',
            'name' => 'Flow Off',
            'steps' => [
                ['step' => 1, 'role' => 'transfers_manager'],
            ],
        ], ['X-Idempotency-Key' => 'wf-fd-create'])->json('data.flow_id');

        $this->postJson('/api/v1/workflow/approval-flows/'.$flowId.'/deactivate', [], [
            'X-Idempotency-Key' => 'wf-fd-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas(SchemaHelper::qualified('workflow', 'approval_flows'), [
            'id' => $flowId,
            'is_active' => false,
        ]);
    }
}
