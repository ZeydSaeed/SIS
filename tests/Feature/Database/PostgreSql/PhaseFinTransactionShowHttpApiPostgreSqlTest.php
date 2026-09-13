<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Finance\ValueObjects\FinanceTransactionType;
use App\Domain\Finance\ValueObjects\PaymentMethod;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinTransactionShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,year_id:int,enrollment_id:int,student_id:int,fee_type_id:int}
     */
    private function seedBase(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-TXNS-'.$suffix, 'Txn Show '.$suffix);
        $yearId = $this->createAcademicYear('AY-TXNS-'.$suffix);

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
            'first_name' => 'T',
            'last_name' => 'X',
            'full_name' => 'T X',
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

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'enrollment_id' => $enrollmentId,
            'student_id' => $studentId,
            'fee_type_id' => $feeTypeId,
        ];
    }

    #[Test]
    public function manager_can_show_finance_transaction(): void
    {
        $ctx = $this->seedBase('SH1');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/finance/student-fees', [
            'enrollment_id' => $ctx['enrollment_id'],
            'fee_type_id' => $ctx['fee_type_id'],
            'academic_year_id' => $ctx['year_id'],
        ], ['X-Idempotency-Key' => 'txn-show-assign'])->assertCreated();

        $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => (int) $this->getJson(
                '/api/v1/finance/transactions?student_id='.$ctx['student_id'].'&academic_year_id='.$ctx['year_id']
            )->json('data.0.reference_id'),
            'amount' => '40.00',
            'payment_method' => PaymentMethod::Cash,
        ], ['X-Idempotency-Key' => 'txn-show-pay'])->assertCreated();

        $list = $this->getJson(
            '/api/v1/finance/transactions?student_id='.$ctx['student_id'].'&academic_year_id='.$ctx['year_id']
        )->assertOk();

        $transactionId = (int) $list->json('data.1.id');

        $this->getJson('/api/v1/finance/transactions/'.$transactionId)
            ->assertOk()
            ->assertJsonPath('data.id', $transactionId)
            ->assertJsonPath('data.student_id', $ctx['student_id'])
            ->assertJsonPath('data.academic_year_id', $ctx['year_id'])
            ->assertJsonPath('data.transaction_type', FinanceTransactionType::PaymentReceived)
            ->assertJsonPath('data.amount', '40.00')
            ->assertJsonPath('data.balance_after', '60.00');
    }
}
