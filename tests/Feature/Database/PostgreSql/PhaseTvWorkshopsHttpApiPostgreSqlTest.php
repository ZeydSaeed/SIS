<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Vocational\ValueObjects\WorkshopStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvWorkshopsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function workshops_table_has_force_rls_and_safety_check(): void
    {
        $row = DB::selectOne("
            SELECT c.relrowsecurity AS rls, c.relforcerowsecurity AS force_rls
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'vocational' AND c.relname = 'workshops'
        ");
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->rls);
        $this->assertTrue((bool) $row->force_rls);

        $check = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_constraint
            WHERE conname = 'vocational_workshops_safety_le_capacity_check'
        ");
        $this->assertNotNull($check);
    }

    #[Test]
    public function manager_can_create_and_list_workshop(): void
    {
        $schoolId = $this->createSchool('SCH-WS-1', 'Workshops 1');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $create = $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-a',
            'name' => 'Welding Lab A',
            'capacity' => 24,
            'safety_capacity' => 16,
        ], ['X-Idempotency-Key' => 'ws-create-1'])
            ->assertCreated();

        $workshopId = (int) $create->json('data.workshop_id');

        $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'ws-a',
            'name' => 'Welding Lab A',
            'capacity' => 24,
            'safety_capacity' => 16,
        ], ['X-Idempotency-Key' => 'ws-create-1'])
            ->assertOk()
            ->assertJsonPath('data.workshop_id', $workshopId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/vocational/workshops')
            ->assertOk()
            ->assertJsonPath('data.0.id', $workshopId)
            ->assertJsonPath('data.0.code', 'WS-A')
            ->assertJsonPath('data.0.capacity', 24)
            ->assertJsonPath('data.0.safety_capacity', 16)
            ->assertJsonPath('data.0.status', WorkshopStatus::Active);

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'workshops'), [
            'id' => $workshopId,
            'school_id' => $schoolId,
            'code' => 'WS-A',
            'safety_capacity' => 16,
        ]);
    }

    #[Test]
    public function rejects_safety_capacity_greater_than_capacity(): void
    {
        $schoolId = $this->createSchool('SCH-WS-2', 'Workshops 2');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $this->postJson('/api/v1/vocational/workshops', [
            'code' => 'WS-BAD',
            'name' => 'Unsafe',
            'capacity' => 10,
            'safety_capacity' => 12,
        ], ['X-Idempotency-Key' => 'ws-bad'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'vocational.workshop_safety_exceeds_capacity');
    }
}
