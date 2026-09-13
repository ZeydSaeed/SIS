<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Domain\Vocational\ValueObjects\WorkshopStatus;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvWorkshopShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_workshop(): void
    {
        $schoolId = $this->createSchool('SCH-TV-S1', 'TV Show Workshop');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $workshopId = (int) $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-show',
            'name' => 'Show Workshop',
            'capacity' => 20,
            'safety_capacity' => 12,
        ], ['X-Idempotency-Key' => 'tv-show-ws'])->json('data.workshop_id');

        $this->getJson('/api/v1/vocational/workshops/'.$workshopId)
            ->assertOk()
            ->assertJsonPath('data.id', $workshopId)
            ->assertJsonPath('data.code', 'WS-SHOW')
            ->assertJsonPath('data.name', 'Show Workshop')
            ->assertJsonPath('data.capacity', 20)
            ->assertJsonPath('data.safety_capacity', 12)
            ->assertJsonPath('data.status', WorkshopStatus::Active);
    }
}
