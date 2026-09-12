<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinStudentFeesHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,year_id:int,enrollment_id:int,fee_type_id:int}
     */
    private function seedContext(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-FSF-'.$suffix, 'StudentFee '.$suffix);
        $yearId = $this->createAcademicYear('AY-FSF-'.$suffix);

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
    public function student_fees_table_has_force_rls_and_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'finance' AND table_name = 'student_fees'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);

        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'finance' AND c.relname = 'student_fees'
        ");
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function manager_can_assign_and_list_student_fees(): void
    {
        $ctx = $this->seedContext('A1');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $create = $this->postJson('/api/v1/finance/student-fees', [
            'enrollment_id' => $ctx['enrollment_id'],
            'fee_type_id' => $ctx['fee_type_id'],
            'academic_year_id' => $ctx['year_id'],
            'due_date' => '2026-10-01',
        ], ['X-Idempotency-Key' => 'sf-assign-1'])
            ->assertCreated();

        $studentFeeId = (int) $create->json('data.student_fee_id');

        $this->postJson('/api/v1/finance/student-fees', [
            'enrollment_id' => $ctx['enrollment_id'],
            'fee_type_id' => $ctx['fee_type_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'sf-assign-1'])
            ->assertOk()
            ->assertJsonPath('data.student_fee_id', $studentFeeId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/finance/student-fees?enrollment_id='.$ctx['enrollment_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $studentFeeId)
            ->assertJsonPath('data.0.amount', '250.00')
            ->assertJsonPath('data.0.status', StudentFeeStatus::Unpaid);

        $this->assertDatabaseHas(SchemaHelper::qualified('finance', 'student_fees'), [
            'id' => $studentFeeId,
            'school_id' => $ctx['school_id'],
            'enrollment_id' => $ctx['enrollment_id'],
            'fee_type_id' => $ctx['fee_type_id'],
        ]);
    }

    #[Test]
    public function duplicate_assignment_is_rejected(): void
    {
        $ctx = $this->seedContext('A2');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/finance/student-fees', [
            'enrollment_id' => $ctx['enrollment_id'],
            'fee_type_id' => $ctx['fee_type_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'sf-dup-1'])
            ->assertCreated();

        $this->postJson('/api/v1/finance/student-fees', [
            'enrollment_id' => $ctx['enrollment_id'],
            'fee_type_id' => $ctx['fee_type_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'sf-dup-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'finance.student_fee_duplicate');
    }

    #[Test]
    public function viewer_cannot_assign_student_fee(): void
    {
        $ctx = $this->seedContext('A3');
        $this->actingAsFinanceViewerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/finance/student-fees', [
            'enrollment_id' => $ctx['enrollment_id'],
            'fee_type_id' => $ctx['fee_type_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'sf-deny'])
            ->assertForbidden();
    }
}
