<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfApprovalRequestShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_approval_request(): void
    {
        $schoolId = $this->createSchool('SCH-WF-S2', 'WF Show Request');
        $this->actingAsWorkflowManagerForSchool($schoolId);

        $flowId = (int) $this->postJson('/api/v1/workflow/approval-flows', [
            'entity_type' => 'transfer_request',
            'name' => 'Show Request Flow',
            'steps' => [
                ['step' => 1, 'role' => 'transfers_manager'],
            ],
        ], ['X-Idempotency-Key' => 'wf-show-req-flow'])->json('data.flow_id');

        $requestId = (int) $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $flowId,
            'entity_type' => 'transfer_request',
            'entity_id' => 501,
        ], ['X-Idempotency-Key' => 'wf-show-req'])->json('data.request_id');

        $this->getJson('/api/v1/workflow/approval-requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.flow_id', $flowId)
            ->assertJsonPath('data.entity_type', 'transfer_request')
            ->assertJsonPath('data.entity_id', 501);
    }
}
