<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvWorkshopEquipmentShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_workshop_equipment(): void
    {
        $schoolId = $this->createSchool('SCH-TV-EQS1', 'TV Show Equipment');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $workshopId = (int) $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-eq-show',
            'name' => 'Show Equip Lab',
            'capacity' => 20,
            'safety_capacity' => 12,
        ], ['X-Idempotency-Key' => 'tv-show-eq-ws'])->json('data.workshop_id');

        $equipmentId = (int) $this->postJson('/api/v1/vocational/workshops/'.$workshopId.'/equipment', [
            'code' => 'torch-show',
            'name' => 'Show Torch',
            'quantity' => 4,
        ], ['X-Idempotency-Key' => 'tv-show-eq'])->json('data.equipment_id');

        $this->getJson('/api/v1/vocational/workshop-equipment/'.$equipmentId)
            ->assertOk()
            ->assertJsonPath('data.id', $equipmentId)
            ->assertJsonPath('data.workshop_id', $workshopId)
            ->assertJsonPath('data.code', 'TORCH-SHOW')
            ->assertJsonPath('data.name', 'Show Torch')
            ->assertJsonPath('data.quantity', 4);
    }
}
