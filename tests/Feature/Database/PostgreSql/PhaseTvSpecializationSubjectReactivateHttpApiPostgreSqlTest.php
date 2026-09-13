<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvSpecializationSubjectReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_specialization_subject_link(): void
    {
        $schoolId = $this->createSchool('SCH-SS-R1', 'Subject Link Reactivate');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'S'.substr(uniqid(), -8),
            'name' => 'Circuits',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $specId = (int) $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'ELEC-R',
            'name' => 'Electronics',
        ], ['X-Idempotency-Key' => 'ss-r-spec'])->json('data.id');

        $linkId = (int) $this->postJson('/api/v1/vocational/specializations/'.$specId.'/subjects', [
            'subject_id' => $subjectId,
            'is_required' => true,
            'credit_hours' => 3,
        ], ['X-Idempotency-Key' => 'ss-r-link'])->json('data.id');

        $this->postJson('/api/v1/vocational/specialization-subjects/'.$linkId.'/deactivate', [], [
            'X-Idempotency-Key' => 'ss-r-off',
        ])->assertOk();

        $reactivate = $this->postJson('/api/v1/vocational/specialization-subjects/'.$linkId.'/reactivate', [], [
            'X-Idempotency-Key' => 'ss-r-on',
        ])->assertOk();

        $this->assertSame($linkId, (int) $reactivate->json('data.id'));

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'specialization_subjects'), [
            'id' => $linkId,
            'status' => 1,
        ]);
    }
}
