<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseOrgBranchesHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_branches_for_school(): void
    {
        $schoolId = $this->createSchool('SCH-ORG-BR', 'Branches List');
        $otherSchoolId = $this->createSchool('SCH-ORG-BR-B', 'Branches Other');

        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'MAIN',
            'name' => 'Main Campus',
            'address' => 'Street 1',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('organization', 'branches'))->insert([
            'school_id' => $otherSchoolId,
            'code' => 'OTHER',
            'name' => 'Other Campus',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/organization/branches')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $branchId)
            ->assertJsonPath('data.0.school_id', $schoolId)
            ->assertJsonPath('data.0.code', 'MAIN');
    }
}
