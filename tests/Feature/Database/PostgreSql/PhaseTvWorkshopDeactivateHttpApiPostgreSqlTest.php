<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvWorkshopDeactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_deactivate_workshop(): void
    {
        $schoolId = $this->createSchool('SCH-WS-D1', 'Workshop Deactivate');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $workshopId = (int) $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-off',
            'name' => 'Lab Off',
            'capacity' => 10,
            'safety_capacity' => 8,
        ], ['X-Idempotency-Key' => 'wsd-create'])->json('data.workshop_id');

        $this->postJson('/api/v1/vocational/workshops/'.$workshopId.'/deactivate', [], [
            'X-Idempotency-Key' => 'wsd-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 2);

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'workshops'), [
            'id' => $workshopId,
            'status' => 2,
        ]);
    }
}
