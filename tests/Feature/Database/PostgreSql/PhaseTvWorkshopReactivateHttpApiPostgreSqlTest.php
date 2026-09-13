<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvWorkshopReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_workshop(): void
    {
        $schoolId = $this->createSchool('SCH-WS-R1', 'Workshop Reactivate');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $workshopId = (int) $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-on',
            'name' => 'Lab On',
            'capacity' => 10,
            'safety_capacity' => 8,
        ], ['X-Idempotency-Key' => 'wsr-create'])->json('data.workshop_id');

        $this->postJson('/api/v1/vocational/workshops/'.$workshopId.'/deactivate', [], [
            'X-Idempotency-Key' => 'wsr-off',
        ])->assertOk();

        $this->postJson('/api/v1/vocational/workshops/'.$workshopId.'/reactivate', [], [
            'X-Idempotency-Key' => 'wsr-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'workshops'), [
            'id' => $workshopId,
            'status' => 1,
        ]);
    }
}
