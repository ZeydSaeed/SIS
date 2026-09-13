<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvTrackReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_track(): void
    {
        $schoolId = $this->createSchool('SCH-TRK-R1', 'Track Reactivate');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $specId = (int) $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'elec-tr',
            'name' => 'Electrical',
        ], ['X-Idempotency-Key' => 'trk-r-spec'])->json('data.id');

        $trackId = (int) $this->postJson('/api/v1/vocational/specializations/'.$specId.'/tracks', [
            'code' => 'T1',
            'name' => 'Track One',
        ], ['X-Idempotency-Key' => 'trk-r-create'])->json('data.id');

        $this->postJson('/api/v1/vocational/tracks/'.$trackId.'/deactivate', [], [
            'X-Idempotency-Key' => 'trk-r-off',
        ])->assertOk();

        $reactivate = $this->postJson('/api/v1/vocational/tracks/'.$trackId.'/reactivate', [], [
            'X-Idempotency-Key' => 'trk-r-on',
        ])->assertOk();

        $this->assertSame($trackId, (int) $reactivate->json('data.id'));

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'tracks'), [
            'id' => $trackId,
            'status' => 1,
        ]);
    }
}
