<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTeachSchoolMembershipShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_teacher_school_membership(): void
    {
        $schoolId = $this->createSchool('SCH-MS-U02', 'Teacher School Show');
        $yearId = $this->createAcademicYear('AY-MS-U02');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $teacherId = (int) $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-MS-U02',
            'first_name' => 'Mem',
            'last_name' => 'Show',
        ], ['X-Idempotency-Key' => 'ms-u02-reg'])->json('data.teacher_id');

        $membershipId = (int) $this->getJson('/api/v1/teachers/'.$teacherId.'/schools?academic_year_id='.$yearId)
            ->assertOk()
            ->json('data.0.id');

        $this->getJson('/api/v1/teachers/'.$teacherId.'/schools/'.$membershipId.'?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.id', $membershipId)
            ->assertJsonPath('data.teacher_id', $teacherId)
            ->assertJsonPath('data.school_id', $schoolId)
            ->assertJsonPath('data.academic_year_id', $yearId)
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.left_at', null);
    }
}
