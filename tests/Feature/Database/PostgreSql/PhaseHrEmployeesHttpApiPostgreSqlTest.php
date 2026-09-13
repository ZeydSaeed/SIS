<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Hr\ValueObjects\EmployeeStatus;
use App\Domain\Hr\ValueObjects\JobPositionCategory;
use App\Domain\Hr\ValueObjects\JobPositionStatus;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseHrEmployeesHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function hr_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(Permission::HR_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(Permission::HR_MANAGE, config('security.permissions'));
        $this->assertContains(Permission::HR_MANAGE, config('security.roles.hr_manager'));
    }

    #[Test]
    public function hr_tables_have_force_rls(): void
    {
        foreach (['job_positions', 'employees', 'employee_schools'] as $table) {
            $row = DB::selectOne("
                SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
                FROM pg_class c
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = 'hr' AND c.relname = ?
            ", [$table]);
            $this->assertNotNull($row, $table);
            $this->assertTrue((bool) $row->rls, $table);
            $this->assertTrue((bool) $row->force_rls, $table);
        }
    }

    #[Test]
    public function manager_can_create_position_and_register_employee(): void
    {
        $schoolId = $this->createSchool('SCH-HR-1', 'HR School 1');
        $yearId = $this->createAcademicYear('AY-HR-1');
        $this->actingAsHrManagerForSchool($schoolId);

        $position = $this->postJson('/api/v1/hr/job-positions', [
            'code' => 'admin',
            'name' => 'Administrator',
            'category' => JobPositionCategory::Administrative,
        ], ['X-Idempotency-Key' => 'hr-pos-1'])
            ->assertCreated();

        $positionId = (int) $position->json('data.job_position_id');

        $this->postJson('/api/v1/hr/job-positions', [
            'code' => 'admin',
            'name' => 'Administrator',
            'category' => JobPositionCategory::Administrative,
        ], ['X-Idempotency-Key' => 'hr-pos-1'])
            ->assertOk()
            ->assertJsonPath('data.job_position_id', $positionId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/hr/job-positions')
            ->assertOk()
            ->assertJsonPath('data.0.id', $positionId)
            ->assertJsonPath('data.0.code', 'ADMIN')
            ->assertJsonPath('data.0.status', JobPositionStatus::Active);

        $employee = $this->postJson('/api/v1/hr/employees', [
            'academic_year_id' => $yearId,
            'employee_number' => 'e-hr-001',
            'first_name' => 'Noura',
            'last_name' => 'Hassan',
            'job_position_id' => $positionId,
        ], ['X-Idempotency-Key' => 'hr-emp-1'])
            ->assertCreated();

        $employeeId = (int) $employee->json('data.employee_id');

        $this->postJson('/api/v1/hr/employees', [
            'academic_year_id' => $yearId,
            'employee_number' => 'e-hr-001',
            'first_name' => 'Noura',
            'last_name' => 'Hassan',
            'job_position_id' => $positionId,
        ], ['X-Idempotency-Key' => 'hr-emp-1'])
            ->assertOk()
            ->assertJsonPath('data.employee_id', $employeeId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/hr/employees?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $employeeId)
            ->assertJsonPath('data.0.employee_number', 'E-HR-001')
            ->assertJsonPath('data.0.full_name', 'Noura Hassan')
            ->assertJsonPath('data.0.job_position_id', $positionId)
            ->assertJsonPath('data.0.status', EmployeeStatus::Active);

        $this->assertDatabaseHas(SchemaHelper::qualified('hr', 'employees'), [
            'id' => $employeeId,
            'employee_number' => 'E-HR-001',
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('hr', 'employee_schools'), [
            'employee_id' => $employeeId,
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'job_position_id' => $positionId,
            'is_primary' => true,
        ]);
    }

    #[Test]
    public function duplicate_employee_number_is_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-HR-2', 'HR School 2');
        $yearId = $this->createAcademicYear('AY-HR-2');
        $this->actingAsHrManagerForSchool($schoolId);

        $this->postJson('/api/v1/hr/employees', [
            'academic_year_id' => $yearId,
            'employee_number' => 'E-HR-DUP',
            'first_name' => 'A',
            'last_name' => 'B',
        ], ['X-Idempotency-Key' => 'hr-dup-1'])->assertCreated();

        $this->postJson('/api/v1/hr/employees', [
            'academic_year_id' => $yearId,
            'employee_number' => 'E-HR-DUP',
            'first_name' => 'C',
            'last_name' => 'D',
        ], ['X-Idempotency-Key' => 'hr-dup-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'hr.employee_number_taken');
    }

    #[Test]
    public function manager_can_deactivate_position_and_employee(): void
    {
        $schoolId = $this->createSchool('SCH-HR-4', 'HR School 4');
        $yearId = $this->createAcademicYear('AY-HR-4');
        $this->actingAsHrManagerForSchool($schoolId);

        $positionId = (int) $this->postJson('/api/v1/hr/job-positions', [
            'code' => 'HR-OFF',
            'name' => 'Office',
            'category' => JobPositionCategory::Support,
        ], ['X-Idempotency-Key' => 'hr-off-pos'])
            ->assertCreated()
            ->json('data.job_position_id');

        $employeeId = (int) $this->postJson('/api/v1/hr/employees', [
            'academic_year_id' => $yearId,
            'employee_number' => 'E-HR-OFF',
            'first_name' => 'Omar',
            'last_name' => 'Saleh',
            'job_position_id' => $positionId,
        ], ['X-Idempotency-Key' => 'hr-off-emp'])
            ->assertCreated()
            ->json('data.employee_id');

        $this->postJson('/api/v1/hr/job-positions/'.$positionId.'/deactivate', [], [
            'X-Idempotency-Key' => 'hr-off-pos-deact',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', JobPositionStatus::Inactive);

        $this->postJson('/api/v1/hr/job-positions/'.$positionId.'/deactivate', [], [
            'X-Idempotency-Key' => 'hr-off-pos-deact',
        ])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->postJson('/api/v1/hr/employees/'.$employeeId.'/deactivate', [], [
            'X-Idempotency-Key' => 'hr-off-emp-deact',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeStatus::Inactive);

        $this->assertDatabaseHas(SchemaHelper::qualified('hr', 'job_positions'), [
            'id' => $positionId,
            'status' => JobPositionStatus::Inactive,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('hr', 'employees'), [
            'id' => $employeeId,
            'status' => EmployeeStatus::Inactive,
        ]);
        $this->assertNotNull(
            DB::table(SchemaHelper::qualified('hr', 'employees'))
                ->where('id', $employeeId)
                ->value('effective_to')
        );
    }

    #[Test]
    public function manager_can_reactivate_position_and_employee(): void
    {
        $schoolId = $this->createSchool('SCH-HR-5', 'HR School 5');
        $yearId = $this->createAcademicYear('AY-HR-5');
        $this->actingAsHrManagerForSchool($schoolId);

        $positionId = (int) $this->postJson('/api/v1/hr/job-positions', [
            'code' => 'HR-ON',
            'name' => 'Clerk',
            'category' => JobPositionCategory::Administrative,
        ], ['X-Idempotency-Key' => 'hr-on-pos'])
            ->assertCreated()
            ->json('data.job_position_id');

        $employeeId = (int) $this->postJson('/api/v1/hr/employees', [
            'academic_year_id' => $yearId,
            'employee_number' => 'E-HR-ON',
            'first_name' => 'Lina',
            'last_name' => 'Nasser',
            'job_position_id' => $positionId,
        ], ['X-Idempotency-Key' => 'hr-on-emp'])
            ->assertCreated()
            ->json('data.employee_id');

        $this->postJson('/api/v1/hr/job-positions/'.$positionId.'/deactivate', [], [
            'X-Idempotency-Key' => 'hr-on-pos-off',
        ])->assertOk();
        $this->postJson('/api/v1/hr/employees/'.$employeeId.'/deactivate', [], [
            'X-Idempotency-Key' => 'hr-on-emp-off',
        ])->assertOk();

        $this->postJson('/api/v1/hr/job-positions/'.$positionId.'/reactivate', [], [
            'X-Idempotency-Key' => 'hr-on-pos-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', JobPositionStatus::Active);

        $this->postJson('/api/v1/hr/job-positions/'.$positionId.'/reactivate', [], [
            'X-Idempotency-Key' => 'hr-on-pos-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->postJson('/api/v1/hr/employees/'.$employeeId.'/reactivate', [], [
            'X-Idempotency-Key' => 'hr-on-emp-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', EmployeeStatus::Active);

        $this->assertDatabaseHas(SchemaHelper::qualified('hr', 'job_positions'), [
            'id' => $positionId,
            'status' => JobPositionStatus::Active,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('hr', 'employees'), [
            'id' => $employeeId,
            'status' => EmployeeStatus::Active,
        ]);
        $this->assertNull(
            DB::table(SchemaHelper::qualified('hr', 'employees'))
                ->where('id', $employeeId)
                ->value('effective_to')
        );
    }

    #[Test]
    public function viewer_cannot_register_employee(): void
    {
        $schoolId = $this->createSchool('SCH-HR-3', 'HR School 3');
        $yearId = $this->createAcademicYear('AY-HR-3');
        $this->actingAsHrViewerForSchool($schoolId);

        $this->postJson('/api/v1/hr/employees', [
            'academic_year_id' => $yearId,
            'employee_number' => 'E-HR-VIEW',
            'first_name' => 'X',
            'last_name' => 'Y',
        ], ['X-Idempotency-Key' => 'hr-view-deny'])
            ->assertForbidden();
    }
}
