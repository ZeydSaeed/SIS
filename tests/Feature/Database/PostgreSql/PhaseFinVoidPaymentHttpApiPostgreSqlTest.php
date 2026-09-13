<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Finance\ValueObjects\FinanceTransactionType;
use App\Domain\Finance\ValueObjects\PaymentMethod;
use App\Domain\Finance\ValueObjects\PaymentStatus;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinVoidPaymentHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,year_id:int,enrollment_id:int,fee_type_id:int,student_fee_id:int}
     */
    private function seedFeeContext(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-VOID-'.$suffix, 'Void '.$suffix);
        $yearId = $this->createAcademicYear('AY-VOID-'.$suffix);

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GV-'.$suffix,
            'name' => 'Grade V '.$suffix,
            'level_order' => 1,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'CV-'.$suffix,
            'name' => 'Class V '.$suffix,
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
            'student_code' => 'STV-'.$suffix,
            'first_name' => 'V',
            'last_name' => 'P',
            'full_name' => 'V P',
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
            'enrollment_number' => 'ENV-'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $feeTypeId = (int) DB::table(SchemaHelper::qualified('finance', 'fee_types'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'TUI-V-'.$suffix,
            'name' => 'Tuition V '.$suffix,
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
            'student_id' => $studentId,
        ];
    }

    #[Test]
    public function manager_can_void_posted_payment_and_re_rollup_fee(): void
    {
        $ctx = $this->seedFeeContext('1');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $paymentId = (int) $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '100.00',
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => '2026-09-13T10:00:00+03:00',
        ], ['X-Idempotency-Key' => 'void-pay-rec-1'])
            ->assertCreated()
            ->json('data.payment_id');

        $this->assertDatabaseHas(SchemaHelper::qualified('finance', 'student_fees'), [
            'id' => $ctx['student_fee_id'],
            'status' => StudentFeeStatus::Paid,
        ]);

        $this->postJson('/api/v1/finance/payments/'.$paymentId.'/void', [
            'notes' => 'Duplicate receipt',
        ], ['X-Idempotency-Key' => 'void-pay-1'])
            ->assertOk()
            ->assertJsonPath('data.payment_id', $paymentId)
            ->assertJsonPath('data.student_fee_status', StudentFeeStatus::Unpaid);

        $this->postJson('/api/v1/finance/payments/'.$paymentId.'/void', [
            'notes' => 'Duplicate receipt',
        ], ['X-Idempotency-Key' => 'void-pay-1'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $list = $this->getJson('/api/v1/finance/payments?student_fee_id='.$ctx['student_fee_id'])
            ->assertOk()
            ->assertJsonPath('data.0.status', PaymentStatus::Voided);

        $this->assertNotNull($list->json('data.0.voided_at'));

        $this->assertDatabaseHas(SchemaHelper::qualified('finance', 'student_fees'), [
            'id' => $ctx['student_fee_id'],
            'status' => StudentFeeStatus::Unpaid,
        ]);

        $refund = DB::table(SchemaHelper::qualified('finance', 'transactions'))
            ->where('school_id', $ctx['school_id'])
            ->where('reference_type', 'payment')
            ->where('reference_id', $paymentId)
            ->where('transaction_type', FinanceTransactionType::PaymentRefunded)
            ->first(['amount', 'notes']);
        $this->assertNotNull($refund);
        $this->assertSame('100.00', number_format((float) $refund->amount, 2, '.', ''));
        $this->assertSame('Duplicate receipt', $refund->notes);
    }

    #[Test]
    public function re_void_without_idempotency_is_rejected(): void
    {
        $ctx = $this->seedFeeContext('2');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $paymentId = (int) $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '40.00',
            'payment_method' => PaymentMethod::Card,
            'paid_at' => '2026-09-13T11:00:00+03:00',
        ], ['X-Idempotency-Key' => 'void-pay-rec-2'])->json('data.payment_id');

        $this->postJson('/api/v1/finance/payments/'.$paymentId.'/void', [], [
            'X-Idempotency-Key' => 'void-pay-2a',
        ])->assertOk();

        $this->postJson('/api/v1/finance/payments/'.$paymentId.'/void', [], [
            'X-Idempotency-Key' => 'void-pay-2b',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'finance.payment_not_posted');
    }

    #[Test]
    public function viewer_cannot_void_payment(): void
    {
        $ctx = $this->seedFeeContext('3');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $paymentId = (int) $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '10.00',
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => '2026-09-13T12:00:00+03:00',
        ], ['X-Idempotency-Key' => 'void-pay-rec-3'])->json('data.payment_id');

        $this->actingAsFinanceViewerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/finance/payments/'.$paymentId.'/void', [], [
            'X-Idempotency-Key' => 'void-pay-deny',
        ])->assertForbidden();
    }
}
