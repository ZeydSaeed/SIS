<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfCancelApprovalRequestHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @param  list<array{step:int, role:string}>  $steps
     * @return array{school_id:int,flow_id:int}
     */
    private function seedFlow(string $suffix, array $steps): array
    {
        $schoolId = $this->createSchool('SCH-WFC-'.$suffix, 'WF Cancel '.$suffix);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $table = SchemaHelper::qualified('workflow', 'approval_flows');
        $row = DB::selectOne(
            "INSERT INTO {$table} (school_id, entity_type, name, steps, is_active, created_at)
             VALUES (?, ?, ?, ?::jsonb, true, NOW())
             RETURNING id",
            [
                $schoolId,
                'transfer_request',
                'Cancel flow '.$suffix,
                json_encode($steps, JSON_THROW_ON_ERROR),
            ],
        );

        return [
            'school_id' => $schoolId,
            'flow_id' => (int) $row->id,
        ];
    }

    #[Test]
    public function cancel_pending_request_sets_cancelled(): void
    {
        $ctx = $this->seedFlow('C1', [
            ['step' => 1, 'role' => 'transfers_manager'],
        ]);
        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);

        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 601,
        ], ['X-Idempotency-Key' => 'wfc-create-1'])
            ->assertCreated();

        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/cancel', [], [
            'X-Idempotency-Key' => 'wfc-cn-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Cancelled)
            ->assertJsonPath('data.from_idempotency', false);

        $this->assertDatabaseHas(SchemaHelper::qualified('workflow', 'approval_requests'), [
            'id' => $requestId,
            'status' => ApprovalRequestStatus::Cancelled,
        ]);
    }

    #[Test]
    public function cancel_is_idempotent(): void
    {
        $ctx = $this->seedFlow('C2', [
            ['step' => 1, 'role' => 'transfers_manager'],
        ]);
        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);

        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 602,
        ], ['X-Idempotency-Key' => 'wfc-create-2'])
            ->assertCreated();

        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/cancel', [], [
            'X-Idempotency-Key' => 'wfc-cn-2',
        ])->assertOk();

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/cancel', [], [
            'X-Idempotency-Key' => 'wfc-cn-2',
        ])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);
    }

    #[Test]
    public function cancel_rejects_non_pending(): void
    {
        $ctx = $this->seedFlow('C3', [
            ['step' => 1, 'role' => 'workflow_manager'],
        ]);
        $this->actingAsWorkflowManagerForSchool($ctx['school_id']);

        $create = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 603,
        ], ['X-Idempotency-Key' => 'wfc-create-3'])
            ->assertCreated();

        $requestId = (int) $create->json('data.request_id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wfc-ap-1'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Approved);

        $this->postJson('/api/v1/workflow/approval-requests/'.$requestId.'/cancel', [], [
            'X-Idempotency-Key' => 'wfc-cn-4',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'workflow.approval_request_not_cancellable');

        $create2 = $this->postJson('/api/v1/workflow/approval-requests', [
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => 604,
        ], ['X-Idempotency-Key' => 'wfc-create-4'])
            ->assertCreated();

        $pendingId = (int) $create2->json('data.request_id');
        $this->postJson('/api/v1/workflow/approval-requests/'.$pendingId.'/cancel', [], [
            'X-Idempotency-Key' => 'wfc-cn-5',
        ])->assertOk();

        $this->postJson('/api/v1/workflow/approval-requests/'.$pendingId.'/cancel', [], [
            'X-Idempotency-Key' => 'wfc-cn-6',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'workflow.approval_request_not_cancellable');
    }
}
