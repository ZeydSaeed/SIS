<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase84ChangeTeacherEmployeeCodeHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_change_employee_code(): void
    {
        $schoolId = $this->createSchool('SCH-84-1', 'Code School 1');
        $yearId = $this->createAcademicYear('AY-84-1');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-84-OLD',
            'first_name' => 'Code',
            'last_name' => 'Teacher',
        ], ['X-Idempotency-Key' => '84-reg'])
            ->assertCreated()
            ->json('data.teacher_id');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/change-employee-code', [
            'employee_code' => 't-84-new',
        ], ['X-Idempotency-Key' => '84-chg'])
            ->assertOk()
            ->assertJsonPath('data.employee_code', 'T-84-NEW');

        $this->postJson('/api/v1/teachers/'.$teacherId.'/change-employee-code', [
            'employee_code' => 't-84-new',
        ], ['X-Idempotency-Key' => '84-chg'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->assertDatabaseHas(SchemaHelper::qualified('teachers', 'teachers'), [
            'id' => $teacherId,
            'employee_code' => 'T-84-NEW',
        ]);
    }

    #[Test]
    public function rejects_taken_employee_code(): void
    {
        $schoolId = $this->createSchool('SCH-84-2', 'Code School 2');
        $yearId = $this->createAcademicYear('AY-84-2');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-84-A',
            'first_name' => 'A',
            'last_name' => 'One',
        ], ['X-Idempotency-Key' => '84-a'])->assertCreated();

        $teacherB = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-84-B',
            'first_name' => 'B',
            'last_name' => 'Two',
        ], ['X-Idempotency-Key' => '84-b'])->json('data.teacher_id');

        $this->postJson('/api/v1/teachers/'.$teacherB.'/change-employee-code', [
            'employee_code' => 'T-84-A',
        ], ['X-Idempotency-Key' => '84-taken'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'teachers.employee_code_taken');
    }
}
