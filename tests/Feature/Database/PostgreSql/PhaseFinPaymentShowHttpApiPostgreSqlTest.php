<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Finance\ValueObjects\PaymentMethod;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinPaymentShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,year_id:int,enrollment_id:int,fee_type_id:int,student_fee_id:int}
     */
    private function seedPaidReadyContext(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-PAYS-'.$suffix, 'Payment Show '.$suffix);
        $yearId = $this->createAcademicYear('AY-PAYS-'.$suffix);

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
    public function manager_can_show_payment(): void
    {
        $ctx = $this->seedPaidReadyContext('SH1');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $paymentId = (int) $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '40.00',
            'payment_method' => PaymentMethod::Cash,
        ], ['X-Idempotency-Key' => 'pay-show-1'])->json('data.payment_id');

        $this->getJson('/api/v1/finance/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('data.id', $paymentId)
            ->assertJsonPath('data.student_fee_id', $ctx['student_fee_id'])
            ->assertJsonPath('data.amount', '40.00')
            ->assertJsonPath('data.payment_method', PaymentMethod::Cash);
    }
}
