<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfDecideTransferSyncHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{from_school:int,to_school:int,year_id:int,enrollment_id:int}
     */
    private function seedPair(string $suffix): array
    {
        $fromSchool = $this->createSchool('SCH-WDS-F-'.$suffix, 'Sync From '.$suffix);
        $toSchool = $this->createSchool('SCH-WDS-T-'.$suffix, 'Sync To '.$suffix);
        $yearId = $this->createAcademicYear('AY-WDS-'.$suffix);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $fromSchool]);

        $flowTable = SchemaHelper::qualified('workflow', 'approval_flows');
        DB::selectOne(
            "INSERT INTO {$flowTable} (school_id, entity_type, name, steps, is_active, created_at)
             VALUES (?, 'transfer_request', ?, ?::jsonb, true, NOW())
             RETURNING id",
            [
                $fromSchool,
                'Sync flow '.$suffix,
                json_encode([['step' => 1, 'role' => 'transfers_manager']], JSON_THROW_ON_ERROR),
            ],
        );

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'G-WDS-'.$suffix,
            'name' => 'Grade '.$suffix,
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $fromSchool,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'C-WDS-'.$suffix,
            'name' => 'Class',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionId = (int) DB::table(SchemaHelper::qualified('enrollment', 'sections'))->insertGetId([
            'class_id' => $classId,
            'code' => 'S1',
            'name' => 'S1',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentId = (int) DB::table(SchemaHelper::qualified('students', 'students'))->insertGetId([
            'school_id' => $fromSchool,
            'student_code' => 'ST-WDS-'.$suffix,
            'first_name' => 'D',
            'last_name' => 'S',
            'full_name' => 'D S',
            'gender' => 1,
            'birth_date' => '2012-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentId = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insertGetId([
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'school_id' => $fromSchool,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_number' => 'EN-WDS-'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'from_school' => $fromSchool,
            'to_school' => $toSchool,
            'year_id' => $yearId,
            'enrollment_id' => $enrollmentId,
        ];
    }

    #[Test]
    public function final_workflow_approve_marks_transfer_approved(): void
    {
        $ctx = $this->seedPair('A1');
        $user = $this->actingAsTransfersManagerForSchool($ctx['from_school']);
        app(SecurityPermissionSeeder::class)->assignRole($user, 'workflow_manager', $ctx['from_school']);

        $create = $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'wds-tr-1'])
            ->assertCreated();

        $transferId = (int) $create->json('data.transfer_request_id');

        $approvalId = (int) DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))
            ->where('entity_type', 'transfer_request')
            ->where('entity_id', $transferId)
            ->value('id');
        $this->assertGreaterThan(0, $approvalId);

        $this->postJson('/api/v1/workflow/approval-requests/'.$approvalId.'/decide', [
            'decision' => 'approve',
        ], ['X-Idempotency-Key' => 'wds-dec-1'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Approved);

        $this->assertDatabaseHas(SchemaHelper::qualified('transfers', 'transfer_requests'), [
            'id' => $transferId,
            'status' => TransferRequestStatus::Approved,
        ]);
    }

    #[Test]
    public function final_workflow_reject_marks_transfer_rejected(): void
    {
        $ctx = $this->seedPair('R1');
        $user = $this->actingAsTransfersManagerForSchool($ctx['from_school']);
        app(SecurityPermissionSeeder::class)->assignRole($user, 'workflow_manager', $ctx['from_school']);

        $create = $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'wds-tr-2'])
            ->assertCreated();

        $transferId = (int) $create->json('data.transfer_request_id');
        $approvalId = (int) DB::table(SchemaHelper::qualified('workflow', 'approval_requests'))
            ->where('entity_id', $transferId)
            ->value('id');

        $this->postJson('/api/v1/workflow/approval-requests/'.$approvalId.'/decide', [
            'decision' => 'reject',
        ], ['X-Idempotency-Key' => 'wds-dec-2'])
            ->assertOk()
            ->assertJsonPath('data.status', ApprovalRequestStatus::Rejected);

        $this->assertDatabaseHas(SchemaHelper::qualified('transfers', 'transfer_requests'), [
            'id' => $transferId,
            'status' => TransferRequestStatus::Rejected,
        ]);
    }
}
