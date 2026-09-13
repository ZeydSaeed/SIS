<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvWorkshopEquipmentReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_workshop_equipment(): void
    {
        $schoolId = $this->createSchool('SCH-EQ-R1', 'Equipment Reactivate');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $workshopId = (int) $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-eq',
            'name' => 'Lab EQ',
            'capacity' => 10,
            'safety_capacity' => 8,
        ], ['X-Idempotency-Key' => 'eqr-ws'])->json('data.workshop_id');

        $equipmentId = (int) $this->postJson('/api/v1/vocational/workshops/'.$workshopId.'/equipment', [
            'code' => 'torch-1',
            'name' => 'Torch',
            'quantity' => 2,
        ], ['X-Idempotency-Key' => 'eqr-create'])->json('data.equipment_id');

        $this->postJson('/api/v1/vocational/workshop-equipment/'.$equipmentId.'/deactivate', [], [
            'X-Idempotency-Key' => 'eqr-off',
        ])->assertOk();

        $this->postJson('/api/v1/vocational/workshop-equipment/'.$equipmentId.'/reactivate', [], [
            'X-Idempotency-Key' => 'eqr-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'workshop_equipment'), [
            'id' => $equipmentId,
            'status' => 1,
        ]);
    }
}
