<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Vocational\ValueObjects\VocationalCatalogStatus;
use App\Security\Authorization\Permission;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseTvVocationalHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function vocational_manage_permission_is_registered(): void
    {
        $this->assertArrayHasKey(Permission::VOCATIONAL_MANAGE, config('security.permissions'));
        $this->assertContains(Permission::VOCATIONAL_MANAGE, Permission::all());
        $this->assertContains(Permission::VOCATIONAL_MANAGE, config('security.roles.vocational_manager'));
    }

    #[Test]
    public function manager_can_manage_specialization_track_and_subject_link(): void
    {
        $schoolId = $this->createSchool('SCH-TV-VH', 'Voc HTTP');
        $this->actingAsVocationalManagerForSchool($schoolId);

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'S'.substr(uniqid(), -8),
            'name' => 'Circuits',
            'subject_type' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $spec = $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'ELEC',
            'name' => 'Electronics',
            'description' => 'Family',
        ], [
            'X-Idempotency-Key' => 'voc-http-spec',
        ])->assertCreated();

        $specId = (int) $spec->json('data.id');

        $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'ELEC',
            'name' => 'Electronics',
        ], [
            'X-Idempotency-Key' => 'voc-http-spec',
        ])->assertOk()
            ->assertJsonPath('data.id', $specId)
            ->assertJsonPath('meta.from_idempotency_cache', true);

        $this->patchJson("/api/v1/vocational/specializations/{$specId}", [
            'code' => 'ELEC',
            'name' => 'Electronics Updated',
        ], [
            'X-Idempotency-Key' => 'voc-http-spec-upd',
        ])->assertOk();

        $track = $this->postJson("/api/v1/vocational/specializations/{$specId}/tracks", [
            'code' => 'EL1',
            'name' => 'Electronics 1',
        ], [
            'X-Idempotency-Key' => 'voc-http-track',
        ])->assertCreated();

        $trackId = (int) $track->json('data.id');

        $this->patchJson("/api/v1/vocational/tracks/{$trackId}", [
            'code' => 'EL1',
            'name' => 'Electronics 1B',
        ], [
            'X-Idempotency-Key' => 'voc-http-track-upd',
        ])->assertOk();

        $link = $this->postJson("/api/v1/vocational/specializations/{$specId}/subjects", [
            'subject_id' => $subjectId,
            'is_required' => true,
            'credit_hours' => 3,
        ], [
            'X-Idempotency-Key' => 'voc-http-link',
        ])->assertCreated();

        $linkId = (int) $link->json('data.id');

        $this->postJson("/api/v1/vocational/specialization-subjects/{$linkId}/deactivate", [], [
            'X-Idempotency-Key' => 'voc-http-unlink',
        ])->assertOk();

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'specialization_subjects'), [
            'id' => $linkId,
            'status' => VocationalCatalogStatus::Inactive->value,
        ]);

        $this->postJson("/api/v1/vocational/tracks/{$trackId}/deactivate", [], [
            'X-Idempotency-Key' => 'voc-http-track-off',
        ])->assertOk();

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'tracks'), [
            'id' => $trackId,
            'status' => VocationalCatalogStatus::Inactive->value,
        ]);

        $this->postJson("/api/v1/vocational/specializations/{$specId}/deactivate", [], [
            'X-Idempotency-Key' => 'voc-http-spec-off',
        ])->assertOk();

        $this->assertDatabaseHas(SchemaHelper::qualified('vocational', 'specializations'), [
            'id' => $specId,
            'status' => VocationalCatalogStatus::Inactive->value,
        ]);
    }

    #[Test]
    public function unauthorized_user_cannot_create_specialization(): void
    {
        $schoolId = $this->createSchool('SCH-TV-VD', 'Voc Deny');
        $this->actingAsAttendanceViewer(schoolId: $schoolId);

        $this->postJson('/api/v1/vocational/specializations', [
            'code' => 'X',
            'name' => 'Denied',
        ], [
            'X-Idempotency-Key' => 'voc-http-deny',
        ])->assertForbidden();
    }
}
