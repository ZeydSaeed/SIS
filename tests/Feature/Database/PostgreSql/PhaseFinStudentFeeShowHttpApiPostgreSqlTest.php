<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinStudentFeeShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,year_id:int,enrollment_id:int,fee_type_id:int}
     */
    private function seedContext(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-SFS-'.$suffix, 'StudentFee Show '.$suffix);
        $yearId = $this->createAcademicYear('AY-SFS-'.$suffix);

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'G-'.$suffix,
            'name' => 'Grade '.$suffix,
            'level_order' => 1,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'C-'.$suffix,
            'name' => 'Class '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionId = (int) DB::table(SchemaHelper::qualified('enrollment', 'sections'))->insertGetId([
            'class_id' => $classId,
            'code' => 'S1',
            'name' => 'Section 1',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentId = (int) DB::table(SchemaHelper::qualified('students', 'students'))->insertGetId([
            'school_id' => $schoolId,
            'student_code' => 'ST-'.$suffix,
            'first_name' => 'F',
            'last_name' => 'S',
            'full_name' => 'F S',
            'gender' => 1,
            'birth_date' => '2012-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentId = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insertGetId([
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_number' => 'EN-'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $feeTypeId = (int) DB::table(SchemaHelper::qualified('finance', 'fee_types'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'TUI-'.$suffix,
            'name' => 'Tuition '.$suffix,
            'amount' => '250.00',
            'is_recurring' => true,
            'status' => 1,
            'created_at' => now(),
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'enrollment_id' => $enrollmentId,
            'fee_type_id' => $feeTypeId,
        ];
    }

    #[Test]
    public function manager_can_show_student_fee(): void
    {
        $ctx = $this->seedContext('SH1');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $studentFeeId = (int) $this->postJson('/api/v1/finance/student-fees', [
            'enrollment_id' => $ctx['enrollment_id'],
            'fee_type_id' => $ctx['fee_type_id'],
            'academic_year_id' => $ctx['year_id'],
            'due_date' => '2026-10-01',
        ], ['X-Idempotency-Key' => 'sf-show-1'])->json('data.student_fee_id');

        $this->getJson('/api/v1/finance/student-fees/'.$studentFeeId)
            ->assertOk()
            ->assertJsonPath('data.id', $studentFeeId)
            ->assertJsonPath('data.enrollment_id', $ctx['enrollment_id'])
            ->assertJsonPath('data.fee_type_id', $ctx['fee_type_id'])
            ->assertJsonPath('data.amount', '250.00')
            ->assertJsonPath('data.status', StudentFeeStatus::Unpaid);
    }
}
