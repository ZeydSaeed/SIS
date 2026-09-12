<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Workflow\ValueObjects\ApprovalRequestStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseWfTransferApprovalHookHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{from_school:int,to_school:int,year_id:int,enrollment_id:int,flow_id:int}
     */
    private function seedWithFlow(string $suffix): array
    {
        $fromSchool = $this->createSchool('SCH-WFH-F-'.$suffix, 'Hook From '.$suffix);
        $toSchool = $this->createSchool('SCH-WFH-T-'.$suffix, 'Hook To '.$suffix);
        $yearId = $this->createAcademicYear('AY-WFH-'.$suffix);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $fromSchool]);

        $flowTable = SchemaHelper::qualified('workflow', 'approval_flows');
        $flow = DB::selectOne(
            "INSERT INTO {$flowTable} (school_id, entity_type, name, steps, is_active, created_at)
             VALUES (?, 'transfer_request', ?, ?::jsonb, true, NOW())
             RETURNING id",
            [
                $fromSchool,
                'Transfer hook flow '.$suffix,
                json_encode([['step' => 1, 'role' => 'transfers_manager']], JSON_THROW_ON_ERROR),
            ],
        );

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'G-WFH-'.$suffix,
            'name' => 'Grade '.$suffix,
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $fromSchool,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'C-WFH-'.$suffix,
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
            'student_code' => 'ST-WFH-'.$suffix,
            'first_name' => 'H',
            'last_name' => 'K',
            'full_name' => 'H K',
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
            'enrollment_number' => 'EN-WFH-'.$suffix,
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
            'flow_id' => (int) $flow->id,
        ];
    }

    #[Test]
    public function creating_transfer_opens_approval_request_when_flow_exists(): void
    {
        $ctx = $this->seedWithFlow('H1');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $create = $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
            'reason' => 'Hook test',
        ], ['X-Idempotency-Key' => 'wfh-create-1'])
            ->assertCreated();

        $transferId = (int) $create->json('data.transfer_request_id');

        $this->assertDatabaseHas(SchemaHelper::qualified('workflow', 'approval_requests'), [
            'school_id' => $ctx['from_school'],
            'flow_id' => $ctx['flow_id'],
            'entity_type' => 'transfer_request',
            'entity_id' => $transferId,
            'status' => ApprovalRequestStatus::Pending,
            'current_step' => 1,
        ]);
    }

    #[Test]
    public function creating_transfer_skips_hook_when_disabled(): void
    {
        config(['sis.workflow.auto_hooks.transfer_request' => false]);
        $ctx = $this->seedWithFlow('H2');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $create = $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'wfh-create-2'])
            ->assertCreated();

        $transferId = (int) $create->json('data.transfer_request_id');

        $this->assertDatabaseMissing(SchemaHelper::qualified('workflow', 'approval_requests'), [
            'entity_type' => 'transfer_request',
            'entity_id' => $transferId,
        ]);
    }
}
