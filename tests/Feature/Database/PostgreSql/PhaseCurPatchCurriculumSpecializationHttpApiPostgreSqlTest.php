<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurPatchCurriculumSpecializationHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_assign_and_clear_curriculum_specialization(): void
    {
        $schoolId = $this->createSchool('SCH-CUR8', 'CUR8 School');
        $yearId = $this->createAcademicYear('AY-CUR8');
        $gradeId = $this->createGradeLevel('G-CUR8');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $specId = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'MECH-CUR8',
            'name' => 'Mechanical',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'General Plan CUR8',
        ], ['X-Idempotency-Key' => 'cur8-create'])
            ->assertCreated()
            ->json('data.curriculum_id');

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curricula'), [
            'id' => $curriculumId,
            'specialization_id' => null,
        ]);

        $this->patchJson('/api/v1/curriculum/curricula/'.$curriculumId, [
            'specialization_id' => $specId,
        ], ['X-Idempotency-Key' => 'cur8-assign'])
            ->assertOk()
            ->assertJsonPath('data.curriculum_id', $curriculumId)
            ->assertJsonPath('data.specialization_id', $specId);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curricula'), [
            'id' => $curriculumId,
            'specialization_id' => $specId,
        ]);

        $this->patchJson('/api/v1/curriculum/curricula/'.$curriculumId, [
            'specialization_id' => $specId,
        ], ['X-Idempotency-Key' => 'cur8-assign'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->patchJson('/api/v1/curriculum/curricula/'.$curriculumId, [
            'specialization_id' => null,
        ], ['X-Idempotency-Key' => 'cur8-clear'])
            ->assertOk()
            ->assertJsonPath('data.specialization_id', null);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curricula'), [
            'id' => $curriculumId,
            'specialization_id' => null,
        ]);

        $this->patchJson('/api/v1/curriculum/curricula/'.$curriculumId, [
            'specialization_id' => 999999,
        ], ['X-Idempotency-Key' => 'cur8-bad'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.specialization_invalid');
    }
}
