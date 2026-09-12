<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Finance\ValueObjects\PaymentMethod;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinPaymentsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,year_id:int,enrollment_id:int,fee_type_id:int,student_fee_id:int}
     */
    private function seedPaidReadyContext(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-PAY-'.$suffix, 'Payment '.$suffix);
        $yearId = $this->createAcademicYear('AY-PAY-'.$suffix);

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
            'first_name' => 'P',
            'last_name' => 'Y',
            'full_name' => 'P Y',
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
            'amount' => '100.00',
            'is_recurring' => true,
            'status' => 1,
            'created_at' => now(),
        ]);
        $studentFeeId = (int) DB::table(SchemaHelper::qualified('finance', 'student_fees'))->insertGetId([
            'school_id' => $schoolId,
            'enrollment_id' => $enrollmentId,
            'fee_type_id' => $feeTypeId,
            'academic_year_id' => $yearId,
            'amount' => '100.00',
            'due_date' => '2026-10-01',
            'status' => StudentFeeStatus::Unpaid,
            'created_at' => now(),
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'enrollment_id' => $enrollmentId,
            'fee_type_id' => $feeTypeId,
            'student_fee_id' => $studentFeeId,
        ];
    }

    #[Test]
    public function payments_table_has_force_rls_and_school_id(): void
    {
        $cols = collect(DB::select("
            SELECT column_name FROM information_schema.columns
            WHERE table_schema = 'finance' AND table_name = 'payments'
        "))->pluck('column_name')->all();

        $this->assertContains('school_id', $cols);

        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls_enabled, c.relforcerowsecurity AS rls_forced
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'finance' AND c.relname = 'payments'
        ");
        $this->assertTrue((bool) $row->rls_enabled);
        $this->assertTrue((bool) $row->rls_forced);
    }

    #[Test]
    public function manager_can_record_partial_then_full_payment(): void
    {
        $ctx = $this->seedPaidReadyContext('P1');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $partial = $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '40.00',
            'payment_method' => PaymentMethod::Cash,
        ], ['X-Idempotency-Key' => 'pay-1'])
            ->assertCreated()
            ->assertJsonPath('data.student_fee_status', StudentFeeStatus::Partial);

        $paymentId = (int) $partial->json('data.payment_id');

        $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '40.00',
            'payment_method' => PaymentMethod::Cash,
        ], ['X-Idempotency-Key' => 'pay-1'])
            ->assertOk()
            ->assertJsonPath('data.payment_id', $paymentId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '60.00',
            'payment_method' => PaymentMethod::BankTransfer,
            'payment_reference' => 'TRX-1',
        ], ['X-Idempotency-Key' => 'pay-2'])
            ->assertCreated()
            ->assertJsonPath('data.student_fee_status', StudentFeeStatus::Paid);

        $this->getJson('/api/v1/finance/payments?student_fee_id='.$ctx['student_fee_id'])
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertDatabaseHas(SchemaHelper::qualified('finance', 'student_fees'), [
            'id' => $ctx['student_fee_id'],
            'status' => StudentFeeStatus::Paid,
        ]);
    }

    #[Test]
    public function overpayment_is_rejected(): void
    {
        $ctx = $this->seedPaidReadyContext('P2');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '150.00',
            'payment_method' => PaymentMethod::Cash,
        ], ['X-Idempotency-Key' => 'pay-over'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'finance.payment_exceeds_remaining');
    }

    #[Test]
    public function viewer_cannot_record_payment(): void
    {
        $ctx = $this->seedPaidReadyContext('P3');
        $this->actingAsFinanceViewerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '10.00',
            'payment_method' => PaymentMethod::Cash,
        ], ['X-Idempotency-Key' => 'pay-deny'])
            ->assertForbidden();
    }
}
