<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseOrgDepartmentsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_departments_for_school(): void
    {
        $schoolId = $this->createSchool('SCH-ORG-DP', 'Departments List');
        $otherSchoolId = $this->createSchool('SCH-ORG-D2', 'Departments Other');

        $departmentId = (int) DB::table(SchemaHelper::qualified('organization', 'departments'))->insertGetId([
            'school_id' => $schoolId,
            'branch_id' => null,
            'code' => 'VOC',
            'name' => 'Vocational',
            'department_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('organization', 'departments'))->insert([
            'school_id' => $otherSchoolId,
            'branch_id' => null,
            'code' => 'OTH',
            'name' => 'Other Dept',
            'department_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/organization/departments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $departmentId)
            ->assertJsonPath('data.0.school_id', $schoolId)
            ->assertJsonPath('data.0.code', 'VOC')
            ->assertJsonPath('data.0.department_type', 1);
    }
}
