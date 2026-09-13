<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvWorkshopEquipmentDeactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_deactivate_workshop_equipment(): void
    {
        $schoolId = $this->createSchool('SCH-EQ-D1', 'Equipment Deactivate');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $workshopId = (int) $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-eqd',
            'name' => 'Lab',
            'capacity' => 10,
            'safety_capacity' => 8,
        ], ['X-Idempotency-Key' => 'eqd-ws'])->json('data.workshop_id');

        $equipmentId = (int) $this->postJson('/api/v1/vocational/workshops/'.$workshopId.'/equipment', [
            'code' => 'mask',
            'name' => 'Mask',
            'quantity' => 2,
        ], ['X-Idempotency-Key' => 'eqd-create'])->json('data.equipment_id');

        $this->postJson('/api/v1/vocational/workshop-equipment/'.$equipmentId.'/deactivate', [], [
            'X-Idempotency-Key' => 'eqd-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 2);

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'workshop_equipment'), [
            'id' => $equipmentId,
            'status' => 2,
        ]);
    }
}
