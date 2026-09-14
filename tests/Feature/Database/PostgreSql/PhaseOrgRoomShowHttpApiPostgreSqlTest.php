<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseOrgRoomShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_room_in_school(): void
    {
        $schoolId = $this->createSchool('SCH-ORG-ROOM2', 'Room Show');
        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'BR1',
            'name' => 'Branch',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $roomId = (int) DB::table(SchemaHelper::qualified('organization', 'rooms'))->insertGetId([
            'branch_id' => $branchId,
            'code' => 'LAB1',
            'name' => 'Lab 1',
            'capacity' => 24,
            'room_type' => 2,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/organization/rooms/'.$roomId)
            ->assertOk()
            ->assertJsonPath('data.id', $roomId)
            ->assertJsonPath('data.code', 'LAB1')
            ->assertJsonPath('data.school_id', $schoolId)
            ->assertJsonPath('data.capacity', 24);
    }

    #[Test]
    public function show_returns_404_for_foreign_or_missing_room(): void
    {
        $schoolId = $this->createSchool('SCH-ROOM404', 'Room Missing');
        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/organization/rooms/999999001')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'organization.room_not_found');
    }
}
