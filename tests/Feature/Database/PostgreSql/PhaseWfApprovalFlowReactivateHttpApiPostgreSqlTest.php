<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfApprovalFlowReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_approval_flow(): void
    {
        $schoolId = $this->createSchool('SCH-WF-R1', 'Flow Reactivate');
        $this->actingAsWorkflowManagerForSchool($schoolId);

        $flowId = (int) $this->postJson('/api/v1/workflow/approval-flows', [
            'entity_type' => 'transfer_request',
            'name' => 'Flow On',
            'steps' => [
                ['step' => 1, 'role' => 'transfers_manager'],
            ],
        ], ['X-Idempotency-Key' => 'wf-fr-create'])->json('data.flow_id');

        $this->postJson('/api/v1/workflow/approval-flows/'.$flowId.'/deactivate', [], [
            'X-Idempotency-Key' => 'wf-fr-off',
        ])->assertOk();

        $this->postJson('/api/v1/workflow/approval-flows/'.$flowId.'/reactivate', [], [
            'X-Idempotency-Key' => 'wf-fr-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas(SchemaHelper::qualified('workflow', 'approval_flows'), [
            'id' => $flowId,
            'is_active' => true,
        ]);
    }
}
