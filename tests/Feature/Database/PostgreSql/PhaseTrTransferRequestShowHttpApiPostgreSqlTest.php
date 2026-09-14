<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTrTransferRequestShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_transfer_request(): void
    {
        $fromSchool = $this->createSchool('SCH-TR-U06-F', 'From U06');
        $toSchool = $this->createSchool('SCH-TR-U06-T', 'To U06');
        $yearId = $this->createAcademicYear('AY-TR-U06');
        $this->actingAsTransfersManagerForSchool($fromSchool);

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'G-TR-U06',
            'name' => 'Grade U06',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $fromSchool,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'C-U06',
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
            'student_code' => 'ST-TR-U06',
            'first_name' => 'T',
            'last_name' => 'S',
            'full_name' => 'T S',
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
            'enrollment_number' => 'EN-TR-U06',
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $requestId = (int) $this->postJson('/api/v1/transfers/requests', [
            'to_school_id' => $toSchool,
            'from_enrollment_id' => $enrollmentId,
            'academic_year_id' => $yearId,
            'reason' => 'Show U06',
        ], ['X-Idempotency-Key' => 'tr-u06-create'])->json('data.transfer_request_id');

        $this->getJson('/api/v1/transfers/requests/'.$requestId)
            ->assertOk()
            ->assertJsonPath('data.id', $requestId)
            ->assertJsonPath('data.from_school_id', $fromSchool)
            ->assertJsonPath('data.to_school_id', $toSchool)
            ->assertJsonPath('data.status', TransferRequestStatus::Pending)
            ->assertJsonPath('data.reason', 'Show U06');
    }
}
