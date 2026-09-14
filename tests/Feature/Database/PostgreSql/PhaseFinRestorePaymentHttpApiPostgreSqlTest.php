<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Finance\ValueObjects\PaymentMethod;
use App\Domain\Finance\ValueObjects\PaymentStatus;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseFinRestorePaymentHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,student_fee_id:int}
     */
    private function seedFeeContext(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-RST-'.$suffix, 'Restore '.$suffix);
        $yearId = $this->createAcademicYear('AY-RST-'.$suffix);

        $gradeId = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GR-'.$suffix,
            'name' => 'Grade R '.$suffix,
            'level_order' => 1,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'code' => 'CR-'.$suffix,
            'name' => 'Class R '.$suffix,
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
            'student_code' => 'STR-'.$suffix,
            'first_name' => 'R',
            'last_name' => 'P',
            'full_name' => 'R P',
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
            'enrollment_number' => 'ENR-'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $feeTypeId = (int) DB::table(SchemaHelper::qualified('finance', 'fee_types'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'TUI-R-'.$suffix,
            'name' => 'Tuition R '.$suffix,
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
            'student_fee_id' => $studentFeeId,
        ];
    }

    #[Test]
    public function manager_can_restore_voided_payment_and_re_rollup_fee(): void
    {
        $ctx = $this->seedFeeContext('1');
        $this->actingAsFinanceManagerForSchool($ctx['school_id']);

        $paymentId = (int) $this->postJson('/api/v1/finance/payments', [
            'student_fee_id' => $ctx['student_fee_id'],
            'amount' => '100.00',
            'payment_method' => PaymentMethod::Cash,
            'paid_at' => '2026-09-13T10:00:00+03:00',
        ], ['X-Idempotency-Key' => 'rst-pay-rec-1'])->json('data.payment_id');

        $this->postJson('/api/v1/finance/payments/'.$paymentId.'/void', [], [
            'X-Idempotency-Key' => 'rst-pay-void-1',
        ])->assertOk();

        $this->postJson('/api/v1/finance/payments/'.$paymentId.'/restore', [], [
            'X-Idempotency-Key' => 'rst-pay-1',
        ])
            ->assertOk()
            ->assertJsonPath('data.payment_id', $paymentId)
            ->assertJsonPath('data.student_fee_status', StudentFeeStatus::Paid);

        $this->getJson('/api/v1/finance/payments/'.$paymentId)
            ->assertOk()
            ->assertJsonPath('data.status', PaymentStatus::Posted)
            ->assertJsonPath('data.voided_at', null);

        $this->assertDatabaseHas(SchemaHelper::qualified('finance', 'student_fees'), [
            'id' => $ctx['student_fee_id'],
            'status' => StudentFeeStatus::Paid,
        ]);
    }
}
