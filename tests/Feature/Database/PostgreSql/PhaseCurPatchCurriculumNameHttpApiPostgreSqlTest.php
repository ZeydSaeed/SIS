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

final class PhaseCurPatchCurriculumNameHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_rename_curriculum_and_combine_with_specialization(): void
    {
        $schoolId = $this->createSchool('SCH-CUR9', 'CUR9 School');
        $yearId = $this->createAcademicYear('AY-CUR9');
        $gradeId = $this->createGradeLevel('G-CUR9');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $specId = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'CIV-CUR9',
            'name' => 'Civil',
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
            'name' => 'Old Plan CUR9',
        ], ['X-Idempotency-Key' => 'cur9-create'])
            ->assertCreated()
            ->json('data.curriculum_id');

        $this->patchJson('/api/v1/curriculum/curricula/'.$curriculumId, [], [
            'X-Idempotency-Key' => 'cur9-empty',
        ])->assertStatus(422);

        $this->patchJson('/api/v1/curriculum/curricula/'.$curriculumId, [
            'name' => 'Renamed Plan CUR9',
        ], ['X-Idempotency-Key' => 'cur9-rename'])
            ->assertOk()
            ->assertJsonPath('data.fields.name', 'Renamed Plan CUR9');

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curricula'), [
            'id' => $curriculumId,
            'name' => 'Renamed Plan CUR9',
            'specialization_id' => null,
        ]);

        $this->patchJson('/api/v1/curriculum/curricula/'.$curriculumId, [
            'name' => 'Civil Plan CUR9',
            'specialization_id' => $specId,
        ], ['X-Idempotency-Key' => 'cur9-both'])
            ->assertOk()
            ->assertJsonPath('data.fields.name', 'Civil Plan CUR9')
            ->assertJsonPath('data.fields.specialization_id', $specId);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curricula'), [
            'id' => $curriculumId,
            'name' => 'Civil Plan CUR9',
            'specialization_id' => $specId,
        ]);

        $this->getJson('/api/v1/curriculum/curricula?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $curriculumId,
                'name' => 'Civil Plan CUR9',
                'specialization_id' => $specId,
            ]);
    }
}
