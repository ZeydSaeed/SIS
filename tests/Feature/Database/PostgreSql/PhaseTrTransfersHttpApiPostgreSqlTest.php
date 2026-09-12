<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTrTransfersHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{from_school:int,to_school:int,year_id:int,enrollment_id:int}
     */
    private function seedPair(string $suffix): array
    {
        $fromSchool = $this->createSchool('SCH-TR-F-'.$suffix, 'From '.$suffix);
        $toSchool = $this->createSchool('SCH-TR-T-'.$suffix, 'To '.$suffix);
        $yearId = $this->createAcademicYear('AY-TR-'.$suffix);

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'G-TR-'.$suffix,
            'name' => 'Grade '.$suffix,
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $fromSchool,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'C-'.$suffix,
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
            'student_code' => 'ST-TR-'.$suffix,
            'first_name' => 'T',
            'last_name' => 'R',
            'full_name' => 'T R',
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
            'enrollment_number' => 'EN-TR-'.$suffix,
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
    public function transfers_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::TRANSFERS_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::TRANSFERS_MANAGE, config('security.permissions'));
        $this->assertContains(Permission::TRANSFERS_MANAGE, config('security.roles.transfers_manager'));
    }

    #[Test]
    public function from_school_creates_and_to_school_approves(): void
    {
        $ctx = $this->seedPair('A1');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $create = $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
            'reason' => 'Family move',
        ], ['X-Idempotency-Key' => 'tr-create-1'])
            ->assertCreated();

        $requestId = (int) $create->json('data.transfer_request_id');

        $this->getJson('/api/v1/transfers/requests?academic_year_id='.$ctx['year_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $requestId)
            ->assertJsonPath('data.0.status', TransferRequestStatus::Pending);

        $this->actingAsTransfersManagerForSchool($ctx['to_school']);

        $this->getJson('/api/v1/transfers/requests?request_status=1')
            ->assertOk()
            ->assertJsonPath('data.0.id', $requestId);

        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/approve', [], [
            'X-Idempotency-Key' => 'tr-approve-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TransferRequestStatus::Approved);

        $this->assertDatabaseHas(SchemaHelper::qualified('transfers', 'transfer_requests'), [
            'id' => $requestId,
            'status' => TransferRequestStatus::Approved,
        ]);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $ctx['enrollment_id'],
            'school_id' => $ctx['from_school'],
            'status' => 1,
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function from_school_cannot_approve_destination_request(): void
    {
        $ctx = $this->seedPair('A2');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $requestId = (int) $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'tr-create-2'])->json('data.transfer_request_id');

        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/approve', [], [
            'X-Idempotency-Key' => 'tr-approve-deny',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'transfers.request_not_found');
    }

    #[Test]
    public function to_school_can_complete_approved_transfer(): void
    {
        $ctx = $this->seedPair('C1');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $requestId = (int) $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'tr-c-create'])->json('data.transfer_request_id');

        $this->actingAsTransfersManagerForSchool($ctx['to_school']);
        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/approve', [], [
            'X-Idempotency-Key' => 'tr-c-approve',
        ])->assertOk();

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GTC1D',
            'name' => 'Dest Grade',
            'level_order' => 11,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $ctx['to_school'],
            'academic_year_id' => $ctx['year_id'],
            'grade_level_id' => $gradeId,
            'code' => 'C-DEST',
            'name' => 'Dest Class',
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

        $complete = $this->postJson('/api/v1/transfers/requests/'.$requestId.'/complete', [
            'to_class_id' => $classId,
            'to_section_id' => $sectionId,
            'effective_date' => '2026-10-01',
        ], ['X-Idempotency-Key' => 'tr-c-complete'])
            ->assertCreated();

        $toEnrollmentId = (int) $complete->json('data.to_enrollment_id');
        $recordId = (int) $complete->json('data.transfer_record_id');

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $ctx['enrollment_id'],
            'status' => \App\Domain\Enrollment\ValueObjects\EnrollmentStatus::TRANSFERRED,
            'effective_to' => '2026-10-01',
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $toEnrollmentId,
            'school_id' => $ctx['to_school'],
            'status' => \App\Domain\Enrollment\ValueObjects\EnrollmentStatus::ACTIVE,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('transfers', 'transfer_records'), [
            'id' => $recordId,
            'transfer_request_id' => $requestId,
            'to_enrollment_id' => $toEnrollmentId,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('transfers', 'transfer_requests'), [
            'id' => $requestId,
            'status' => TransferRequestStatus::Completed,
        ]);

        $studentId = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))
            ->where('id', $toEnrollmentId)
            ->value('student_id');
        $this->assertDatabaseHas(SchemaHelper::qualified('students', 'students'), [
            'id' => $studentId,
            'school_id' => $ctx['to_school'],
        ]);
    }

    #[Test]
    public function from_school_can_cancel_pending_request(): void
    {
        $ctx = $this->seedPair('X1');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $requestId = (int) $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'tr-x-create'])->json('data.transfer_request_id');

        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/cancel', [], [
            'X-Idempotency-Key' => 'tr-x-cancel',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TransferRequestStatus::Cancelled);

        $this->assertDatabaseHas(SchemaHelper::qualified('transfers', 'transfer_requests'), [
            'id' => $requestId,
            'status' => TransferRequestStatus::Cancelled,
        ]);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $ctx['enrollment_id'],
            'status' => 1,
            'effective_to' => null,
        ]);
    }

    #[Test]
    public function cannot_cancel_completed_request(): void
    {
        $ctx = $this->seedPair('X2');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $requestId = (int) $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'tr-x2-create'])->json('data.transfer_request_id');

        $this->actingAsTransfersManagerForSchool($ctx['to_school']);
        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/approve', [], [
            'X-Idempotency-Key' => 'tr-x2-approve',
        ])->assertOk();

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GTX2D',
            'name' => 'Dest X2',
            'level_order' => 12,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $ctx['to_school'],
            'academic_year_id' => $ctx['year_id'],
            'grade_level_id' => $gradeId,
            'code' => 'CX2',
            'name' => 'Dest',
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

        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/complete', [
            'to_class_id' => $classId,
            'to_section_id' => $sectionId,
            'effective_date' => '2026-10-02',
        ], ['X-Idempotency-Key' => 'tr-x2-complete'])->assertCreated();

        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/cancel', [], [
            'X-Idempotency-Key' => 'tr-x2-cancel',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'transfers.not_cancellable');
    }
}
