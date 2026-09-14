<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfApprovalRequestReopenHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reopen_cancelled_approval_request(): void
    {
        $schoolId = $this->createSchool('SCH-WF-REOP', 'WF Reopen');
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $flowId = (int) DB::table(SchemaHelper::qualified('workflow', 'approval_flows'))->insertGetId([
            'school_id' => $schoolId,
            'entity_type' => 'transfer_request',
            'name' => 'Reopen flow',
            'steps' => json_encode([['step' => 1, 'role' => 'workflow_manager']], JSON_THROW_ON_ERROR),
            'is_active' => true,
            'created_at' => now(),
        ]);

        $this->actingAsWorkflowManagerForSchool($schoolId);

        $requestId = (int) $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $flowId,
            'entity_type' => 'transfer_request',
            'entity_id' => 901,
        ], ['X-Idempotency-Key' => 'wf-reop-create'])->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/cancel', [], [
            'X-Idempotency-Key' => 'wf-reop-cancel',
        ])->assertOk();

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/reopen', [], [
            'X-Idempotency-Key' => 'wf-reop-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Pending);

        $this->assertDatabaseHas(SchemaHelper::qualified('workflow', 'approval_requests'), [
            'id' => $requestId,
            'status' => ApprovalRequestStatus::Pending,
        ]);
    }
}
