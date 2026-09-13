<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvWorkshopEquipmentHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function workshop_equipment_has_force_rls(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'vocational' AND c.relname = 'workshop_equipment'
        ");
        $this->assertTrue((bool) $row->rls);
        $this->assertTrue((bool) $row->force_rls);
    }

    #[Test]
    public function manager_can_create_and_list_workshop_equipment(): void
    {
        $schoolId = $this->createSchool('SCH-EQ-1', 'Equipment 1');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $workshopId = (int) $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-eq',
            'name' => 'Equip Lab',
            'capacity' => 20,
            'safety_capacity' => 12,
        ], ['X-Idempotency-Key' => 'eq-ws-1'])
            ->json('data.workshop_id');

        $equipmentId = (int) $this->postJson('/api/v1/vocational/workshops/'.$workshopId.'/equipment', [
            'code' => 'torch-1',
            'name' => 'Welding Torch',
            'quantity' => 4,
        ], ['X-Idempotency-Key' => 'eq-create-1'])
            ->assertCreated()
            ->json('data.equipment_id');

        $this->postJson('/api/v1/vocational/workshops/'.$workshopId.'/equipment', [
            'code' => 'torch-1',
            'name' => 'Welding Torch',
            'quantity' => 4,
        ], ['X-Idempotency-Key' => 'eq-create-1'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/vocational/workshops/'.$workshopId.'/equipment')
            ->assertOk()
            ->assertJsonPath('data.0.id', $equipmentId)
            ->assertJsonPath('data.0.quantity', 4);

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'workshop_equipment'), [
            'id' => $equipmentId,
            'school_id' => $schoolId,
            'workshop_id' => $workshopId,
            'code' => 'TORCH-1',
        ]);
    }
}
