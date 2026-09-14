<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Vocational\ValueObjects\VocationalCatalogStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvSpecializationSubjectsIndexHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_list_specialization_subject_links(): void
    {
        $schoolId = $this->createSchool('SCH-TV-SS-IDX', 'Spec Subjects Index');
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
            'code' => 'ELEC-IDX',
            'name' => 'Electronics',
        ], ['X-Idempotency-Key' => 'tv-ss-idx-spec'])->json('data.id');

        $linkId = (int) $this->postJson('/api/v1/vocational/specializations/'.$specId.'/subjects', [
            'subject_id' => $subjectId,
            'is_required' => true,
            'credit_hours' => 3,
        ], ['X-Idempotency-Key' => 'tv-ss-idx-link'])->json('data.id');

        $this->getJson('/api/v1/vocational/specializations/'.$specId.'/subjects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $linkId)
            ->assertJsonPath('data.0.subject_id', $subjectId)
            ->assertJsonPath('data.0.status', VocationalCatalogStatus::Active->value);
    }
}
