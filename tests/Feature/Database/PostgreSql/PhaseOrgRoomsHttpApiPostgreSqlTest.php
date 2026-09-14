<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseOrgRoomsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_rooms_for_school_and_filter_by_branch(): void
    {
        $schoolId = $this->createSchool('SCH-ORG-ROOM', 'Rooms List');
        $otherSchoolId = $this->createSchool('SCH-ORG-ROOM-B', 'Rooms Other');

        $branchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'BR1',
            'name' => 'Main Branch',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherBranchId = (int) DB::table(SchemaHelper::qualified('organization', 'branches'))->insertGetId([
            'school_id' => $otherSchoolId,
            'code' => 'BRX',
            'name' => 'Other Branch',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomId = (int) DB::table(SchemaHelper::qualified('organization', 'rooms'))->insertGetId([
            'branch_id' => $branchId,
            'code' => 'R101',
            'name' => 'Room 101',
            'capacity' => 30,
            'room_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(SchemaHelper::qualified('organization', 'rooms'))->insert([
            'branch_id' => $otherBranchId,
            'code' => 'RX1',
            'name' => 'Foreign Room',
            'capacity' => 10,
            'room_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsEnrollmentManagerForSchool($schoolId);

        $this->getJson('/api/v1/organization/rooms')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $roomId)
            ->assertJsonPath('data.0.school_id', $schoolId)
            ->assertJsonPath('data.0.code', 'R101');

        $this->getJson('/api/v1/organization/rooms?branch_id='.$branchId)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $roomId);
    }
}
