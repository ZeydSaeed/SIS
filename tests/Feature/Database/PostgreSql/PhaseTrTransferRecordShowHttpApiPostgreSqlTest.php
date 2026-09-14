<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTrTransferRecordShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{from_school:int,to_school:int,year_id:int,enrollment_id:int}
     */
    private function seedPair(string $suffix): array
    {
        $fromSchool = $this->createSchool('SCH-TR-RS-F-'.$suffix, 'From '.$suffix);
        $toSchool = $this->createSchool('SCH-TR-RS-T-'.$suffix, 'To '.$suffix);
        $yearId = $this->createAcademicYear('AY-TR-RS-'.$suffix);

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'G-RS-'.$suffix,
            'name' => 'Grade '.$suffix,
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $fromSchool,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'C-RS-'.$suffix,
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
            'student_code' => 'ST-RS-'.$suffix,
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
            'enrollment_number' => 'EN-RS-'.$suffix,
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
    public function viewer_can_show_completed_transfer_record(): void
    {
        $ctx = $this->seedPair('S1');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $requestId = (int) $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'tr-rs-create'])->json('data.transfer_request_id');

        $this->actingAsTransfersManagerForSchool($ctx['to_school']);
        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/approve', [], [
            'X-Idempotency-Key' => 'tr-rs-approve',
        ])->assertOk();

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GRS1D',
            'name' => 'Dest Grade',
            'level_order' => 11,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $ctx['to_school'],
            'academic_year_id' => $ctx['year_id'],
            'grade_level_id' => $gradeId,
            'code' => 'C-D-RS',
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
        ], ['X-Idempotency-Key' => 'tr-rs-complete'])->assertCreated();

        $recordId = (int) $complete->json('data.transfer_record_id');

        $this->actingAsTransfersViewerForSchool($ctx['to_school']);

        $this->getJson('/api/v1/transfers/records/'.$recordId)
            ->assertOk()
            ->assertJsonPath('data.id', $recordId)
            ->assertJsonPath('data.transfer_request_id', $requestId)
            ->assertJsonPath('data.from_school_id', $ctx['from_school'])
            ->assertJsonPath('data.to_school_id', $ctx['to_school']);

        $this->assertDatabaseHas(SchemaHelper::qualified('transfers', 'transfer_requests'), [
            'id' => $requestId,
            'status' => TransferRequestStatus::Completed,
        ]);
    }
}
