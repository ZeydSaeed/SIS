<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvSpecializationTracksIndexHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_tracks_for_specialization(): void
    {
        $schoolId = $this->createSchool('SCH-TV-U26', 'TV List Tracks');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $specId = (int) $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'AUTO-U26',
            'name' => 'Automotive',
        ], ['X-Idempotency-Key' => 'tv-u26-spec'])->json('data.id');

        $trackId = (int) $this->postJson('/api/v1/vocational/specializations/'.$specId.'/tracks', [
            'code' => 'T1-U26',
            'name' => 'Track One U26',
        ], ['X-Idempotency-Key' => 'tv-u26-t1'])->json('data.id');

        $this->getJson('/api/v1/vocational/specializations/'.$specId.'/tracks')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $trackId)
            ->assertJsonPath('data.0.specialization_id', $specId)
            ->assertJsonPath('data.0.code', 'T1-U26')
            ->assertJsonPath('data.0.name', 'Track One U26');
    }
}
