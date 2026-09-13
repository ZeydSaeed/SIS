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

final class PhaseCurCurriculumSpecializationHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_create_curriculum_with_school_specialization(): void
    {
        $schoolId = $this->createSchool('SCH-CUR7', 'CUR7 School');
        $otherSchoolId = $this->createSchool('SCH-CUR7B', 'CUR7 Other');
        $yearId = $this->createAcademicYear('AY-CUR7');
        $gradeId = $this->createGradeLevel('G-CUR7');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $specId = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'ELEC-CUR7',
            'name' => 'Electrical',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inactiveSpecId = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'DEAD-CUR7',
            'name' => 'Inactive Spec',
            'status' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $otherSchoolId]);
        $foreignSpecId = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $otherSchoolId,
            'code' => 'FOREIGN-CUR7',
            'name' => 'Foreign Spec',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'Bad Inactive Spec',
            'specialization_id' => $inactiveSpecId,
        ], ['X-Idempotency-Key' => 'cur7-bad-inactive'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.specialization_invalid');

        $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'Bad Foreign Spec',
            'specialization_id' => $foreignSpecId,
        ], ['X-Idempotency-Key' => 'cur7-bad-foreign'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.specialization_invalid');

        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'Electrical Plan CUR7',
            'specialization_id' => $specId,
        ], ['X-Idempotency-Key' => 'cur7-ok'])
            ->assertCreated()
            ->json('data.curriculum_id');

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curricula'), [
            'id' => $curriculumId,
            'specialization_id' => $specId,
            'status' => 1,
        ]);

        $this->getJson('/api/v1/curriculum/curricula?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonFragment([
                'id' => $curriculumId,
                'specialization_id' => $specId,
                'name' => 'Electrical Plan CUR7',
            ]);
    }
}
