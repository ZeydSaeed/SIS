<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfDecideApprovalRequestHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @param  list<array{step:int, role:string}>  $steps
     * @return array{school_id:int,flow_id:int}
     */
    private function seedFlow(string $suffix, array $steps): array
    {
        $schoolId = $this->createSchool('SCH-WFD-'.$suffix, 'WF Decide '.$suffix);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $table = SchemaHelper::qualified('workflow', 'approval_flows');
        $row = DB::selectOne(
            "INSERT INTO {$table} (school_id, entity_type, name, steps, is_active, created_at)
             VALUES (?, ?, ?, ?::jsonb, true, NOW())
             RETURNING id",
            [
                $schoolId,
                'transfer_request',
                'Decide flow '.$suffix,
                json_encode($steps, JSON_THROW_ON_ERROR),
            ],
        );

        return [
            'school_id' => $schoolId,
            'flow_id' => (int) $row->id,
        ];
    }

    #[Test]
    public function approve_advances_then_finalizes_two_step_flow(): void
    {
        $ctx = $this->seedFlow('D1', [
            ['step' => 1, 'role' => 'workflow_manager'],
            ['step' => 2, 'role' => 'workflow_manager'],
        ]);
        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);

        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 501,
        ], ['X-Idempotency-Key' => 'wfd-create-1'])
            ->assertCreated();

        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-ap-1'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Pending)
            ->assertJsonPath('data.current_step', 2);

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-ap-2'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Approved)
            ->assertJsonPath('data.current_step', 2);

        $this->assertDatabaseHas(SchemaHelper::qualified('workflow', 'approval_requests'), [
            'id' => $requestId,
            'status' => ApprovalRequestStatus::Approved,
        ]);
    }

    #[Test]
    public function reject_finalizes_pending_request(): void
    {
        $ctx = $this->seedFlow('D2', [
            ['step' => 1, 'role' => 'workflow_manager'],
        ]);
        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);

        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 502,
        ], ['X-Idempotency-Key' => 'wfd-create-2'])
            ->assertCreated();

        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'reject',
        ], ['X-Idempotency-Key' => 'wfd-rj-1'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Rejected);

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-rj-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'workflow.approval_request_not_pending');
    }

    #[Test]
    public function decide_is_idempotent(): void
    {
        $ctx = $this->seedFlow('D3', [
            ['step' => 1, 'role' => 'workflow_manager'],
        ]);
        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);

        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 503,
        ], ['X-Idempotency-Key' => 'wfd-create-3'])
            ->assertCreated();

        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-idemp'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Approved)
            ->assertJsonPath('data.from_idempotency', false);

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-idemp'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true)
            ->assertJsonPath('data.status', ApprovalRequestStatus::Approved);
    }

    #[Test]
    public function decide_requires_matching_step_role_and_allows_transfers_manager(): void
    {
        $ctx = $this->seedFlow('D4', [
            ['step' => 1, 'role' => 'transfers_manager'],
        ]);

        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);
        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 504,
        ], ['X-Idempotency-Key' => 'wfd-create-4'])
            ->assertCreated();
        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-role-deny'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'workflow.step_role_mismatch');

        $this->actingAsTransfersManagerForSchool($ctx['school_id']);
        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-role-ok'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Approved);
    }

    #[Test]
    public function two_step_flow_requires_each_step_role(): void
    {
        $ctx = $this->seedFlow('D5', [
            ['step' => 1, 'role' => 'transfers_manager'],
            ['step' => 2, 'role' => 'workflow_manager'],
        ]);

        $manager = $this->actingAsWorkflowManagerForSchool($ctx['school_id']);
        app(SecurityPermissionSeeder::class)->assignRole($manager, 'transfers_manager', $ctx['school_id']);

        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 505,
        ], ['X-Idempotency-Key' => 'wfd-create-5'])
            ->assertCreated();
        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-s1'])
            ->assertOk()
            ->assertJsonPath('data.current_step', 2);

        $this->actingAsTransfersManagerForSchool($ctx['school_id']);
        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-s2-deny'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'workflow.step_role_mismatch');

        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);
        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfd-s2-ok'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Approved);
    }
}
