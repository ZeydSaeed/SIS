<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTrTransferRequestReopenHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{from_school:int,to_school:int,year_id:int,enrollment_id:int}
     */
    private function seedPair(string $suffix): array
    {
        $fromSchool = $this->createSchool('SCH-TR-RO-F-'.$suffix, 'From '.$suffix);
        $toSchool = $this->createSchool('SCH-TR-RO-T-'.$suffix, 'To '.$suffix);
        $yearId = $this->createAcademicYear('AY-TR-RO-'.$suffix);

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'G-RO-'.$suffix,
            'name' => 'Grade '.$suffix,
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $fromSchool,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'C-RO-'.$suffix,
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
            'student_code' => 'ST-RO-'.$suffix,
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
            'enrollment_number' => 'EN-RO-'.$suffix,
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
    public function manager_can_reopen_cancelled_transfer_request(): void
    {
        $ctx = $this->seedPair('R1');
        $this->actingAsTransfersManagerForSchool($ctx['from_school']);

        $requestId = (int) $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $ctx['to_school'],
            'from_enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'tr-ro-create'])->json('data.transfer_request_id');

        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/cancel', [], [
            'X-Idempotency-Key' => 'tr-ro-cancel',
        ])->assertOk();

        $this->postJson('/api/v1/transfers/requests/'.$requestId.'/reopen', [], [
            'X-Idempotency-Key' => 'tr-ro-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', TransferRequestStatus::Pending);

        $this->assertDatabaseHas(SchemaHelper::qualified('transfers', 'transfer_requests'), [
            'id' => $requestId,
            'status' => TransferRequestStatus::Pending,
        ]);
    }
}
