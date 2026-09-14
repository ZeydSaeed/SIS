<?php

namespace Tests\Feature\Database\PostgreSql;

use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvTrackShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_track(): void
    {
        $schoolId = $this->createSchool('SCH-TV-U24', 'TV Show Track');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $specId = (int) $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'mech-u24',
            'name' => 'Mechanical',
        ], ['X-Idempotency-Key' => 'tv-u24-spec'])->json('data.id');

        $trackId = (int) $this->postJson('/api/v1/vocational/specializations/'.$specId.'/tracks', [
            'code' => 'T-U24',
            'name' => 'Track U24',
        ], ['X-Idempotency-Key' => 'tv-u24-track'])->json('data.id');

        $this->getJson('/api/v1/vocational/tracks/'.$trackId)
            ->assertOk()
            ->assertJsonPath('data.id', $trackId)
            ->assertJsonPath('data.specialization_id', $specId)
            ->assertJsonPath('data.code', 'T-U24')
            ->assertJsonPath('data.name', 'Track U24')
            ->assertJsonPath('data.status', 1);
    }
}
