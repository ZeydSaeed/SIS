<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTeachSchoolsIndexHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_teacher_school_memberships(): void
    {
        $schoolId = $this->createSchool('SCH-MS-U01', 'Teacher Schools Index');
        $yearId = $this->createAcademicYear('AY-MS-U01');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-MS-U01',
            'first_name' => 'Mem',
            'last_name' => 'Ship',
        ], ['X-Idempotency-Key' => 'ms-u01-reg'])->json('data.teacher_id');

        $this->getJson('/api/v1/teachers/'.$teacherId.'/schools?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.teacher_id', $teacherId)
            ->assertJsonPath('data.0.school_id', $schoolId)
            ->assertJsonPath('data.0.academic_year_id', $yearId)
            ->assertJsonPath('data.0.is_primary', true)
            ->assertJsonPath('data.0.left_at', null);
    }
}
