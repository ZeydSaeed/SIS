<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvSpecializationSubjectShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_specialization_subject_link(): void
    {
        $schoolId = $this->createSchool('SCH-TV-U25', 'TV Show Subject Link');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'S'.substr(uniqid(), -8),
            'name' => 'Welding',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $specId = (int) $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'WELD-U25',
            'name' => 'Welding Spec',
        ], ['X-Idempotency-Key' => 'tv-u25-spec'])->json('data.id');

        $linkId = (int) $this->postJson('/api/v1/vocational/specializations/'.$specId.'/subjects', [
            'subject_id' => $subjectId,
            'is_required' => true,
            'credit_hours' => 4,
        ], ['X-Idempotency-Key' => 'tv-u25-link'])->json('data.id');

        $this->getJson('/api/v1/vocational/specialization-subjects/'.$linkId)
            ->assertOk()
            ->assertJsonPath('data.id', $linkId)
            ->assertJsonPath('data.specialization_id', $specId)
            ->assertJsonPath('data.subject_id', $subjectId)
            ->assertJsonPath('data.is_required', true)
            ->assertJsonPath('data.credit_hours', 4)
            ->assertJsonPath('data.status', 1);
    }
}
