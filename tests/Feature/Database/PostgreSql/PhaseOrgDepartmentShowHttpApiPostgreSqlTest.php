<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseOrgDepartmentShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_department_in_school(): void
    {
        $schoolId = $this->createSchool('SCH-ORG-DS', 'Department Show');

        $departmentId = (int) DB::table(SchemaHelper::qualified('organization', 'departments'))->insertGetId([
            'school_id' => $schoolId,
            'branch_id' => null,
            'code' => 'GEN',
            'name' => 'General',
            'department_type' => 2,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/organization/departments/'.$departmentId)
            ->assertOk()
            ->assertJsonPath('data.id', $departmentId)
            ->assertJsonPath('data.code', 'GEN')
            ->assertJsonPath('data.school_id', $schoolId)
            ->assertJsonPath('data.department_type', 2);
    }

    #[Test]
    public function show_returns_404_for_foreign_or_missing_department(): void
    {
        $schoolId = $this->createSchool('SCH-DEP404', 'Department Missing');
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/organization/departments/999999001')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'organization.department_not_found');
    }
}
