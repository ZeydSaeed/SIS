<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Security\Authorization\Permission;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class Phase8TeachersHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function teachers_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::TEACHERS_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::TEACHERS_MANAGE, config('security.permissions'));
        $this->assertContains(Permission::TEACHERS_MANAGE, config('security.roles.teachers_manager'));
    }

    #[Test]
    public function manager_can_register_list_and_show_teacher(): void
    {
        $schoolId = $this->createSchool('SCH-8-T1', 'Teachers 1');
        $yearId = $this->createAcademicYear('AY-8-T1');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-001',
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'specialization_field' => 'Math',
        ], ['X-Idempotency-Key' => 'phase8-reg-1'])
            ->assertCreated()
            ->assertJsonPath('data.from_idempotency', false);

        $teacherId = (int) $create->json('data.teacher_id');

        $this->getJson('/api/v1/teachers?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $teacherId)
            ->assertJsonPath('data.0.employee_code', 'T-8-001');

        $this->getJson('/api/v1/teachers/'.$teacherId.'?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.full_name', 'Sara Ali')
            ->assertJsonPath('data.school_id', $schoolId);
    }

    #[Test]
    public function duplicate_employee_code_is_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-8-T2', 'Teachers 2');
        $yearId = $this->createAcademicYear('AY-8-T2');
        $this->actingAsTeachersManagerForSchool($schoolId);

        $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-DUP',
            'first_name' => 'A',
            'last_name' => 'B',
        ], ['X-Idempotency-Key' => 'phase8-dup-1'])->assertCreated();

        $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-DUP',
            'first_name' => 'C',
            'last_name' => 'D',
        ], ['X-Idempotency-Key' => 'phase8-dup-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'teachers.employee_code_taken');
    }

    #[Test]
    public function viewer_cannot_register_teacher(): void
    {
        $schoolId = $this->createSchool('SCH-8-T3', 'Teachers 3');
        $yearId = $this->createAcademicYear('AY-8-T3');
        $this->actingAsTeachersViewerForSchool($schoolId);

        $this->postJson('/api/v1/teachers', [
            'academic_year_id' => $yearId,
            'employee_code' => 'T-8-VIEW',
            'first_name' => 'X',
            'last_name' => 'Y',
        ], ['X-Idempotency-Key' => 'phase8-view-deny'])
            ->assertForbidden();
    }
}
