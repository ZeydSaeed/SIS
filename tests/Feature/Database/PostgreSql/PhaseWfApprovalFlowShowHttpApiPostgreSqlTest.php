<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfApprovalFlowShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_approval_flow(): void
    {
        $schoolId = $this->createSchool('SCH-WF-S1', 'WF Show Flow');
        $this->actingAsWorkflowManagerForSchool($schoolId);

        $flowId = (int) $this->postJson('/api/v1/workflow/approval-flows', [
            'entity_type' => 'transfer_request',
            'name' => 'Show Transfer Flow',
            'steps' => [
                ['step' => 1, 'role' => 'transfers_manager'],
                ['step' => 2, 'role' => 'school_admin'],
            ],
        ], ['X-Idempotency-Key' => 'wf-show-flow'])->json('data.flow_id');

        $this->getJson('/api/v1/workflow/approval-flows/'.$flowId)
            ->assertOk()
            ->assertJsonPath('data.id', $flowId)
            ->assertJsonPath('data.entity_type', 'transfer_request')
            ->assertJsonPath('data.name', 'Show Transfer Flow')
            ->assertJsonPath('data.steps.0.role', 'transfers_manager');
    }
}
