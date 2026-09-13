<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvSpecializationReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_specialization(): void
    {
        $schoolId = $this->createSchool('SCH-SPEC-R1', 'Spec Reactivate');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $specId = (int) $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'elec-r',
            'name' => 'Electrical',
        ], ['X-Idempotency-Key' => 'spec-r-create'])->json('data.id');

        $this->postJson('/api/v1/vocational/specializations/'.$specId.'/deactivate', [], [
            'X-Idempotency-Key' => 'spec-r-off',
        ])->assertOk();

        $this->postJson('/api/v1/vocational/specializations/'.$specId.'/reactivate', [], [
            'X-Idempotency-Key' => 'spec-r-on',
        ])->assertOk();

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'specializations'), [
            'id' => $specId,
            'status' => 1,
        ]);
    }
}
