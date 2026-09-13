<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurReactivateUpdateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_update_and_reactivate_subject_and_curriculum(): void
    {
        $schoolId = $this->createSchool('SCH-CUR4', 'CUR4 School');
        $yearId = $this->createAcademicYear('AY-CUR4');
        $gradeId = $this->createGradeLevel('G-CUR4');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $subjectId = (int) $this->postJson('/api/v1/curriculum/subjects', [
            'code' => 'CHEM-CUR4',
            'name' => 'Chemistry',
            'subject_type' => 1,
        ], ['X-Idempotency-Key' => 'cur4-subj'])->assertCreated()->json('data.subject_id');

        $this->patchJson('/api/v1/curriculum/subjects/'.$subjectId, [
            'name' => 'Chemistry Advanced',
            'pass_grade' => 60,
        ], ['X-Idempotency-Key' => 'cur4-upd'])
            ->assertOk()
            ->assertJsonPath('data.subject_id', $subjectId);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'subjects'), [
            'id' => $subjectId,
            'name' => 'Chemistry Advanced',
            'pass_grade' => 60,
        ]);

        $this->postJson('/api/v1/curriculum/subjects/'.$subjectId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur4-deact-s',
        ])->assertOk();

        $this->postJson('/api/v1/curriculum/subjects/'.$subjectId.'/reactivate', [], [
            'X-Idempotency-Key' => 'cur4-re-s',
        ])->assertOk()->assertJsonPath('data.status', 1);

        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'CUR4 Plan',
        ], ['X-Idempotency-Key' => 'cur4-cur'])->assertCreated()->json('data.curriculum_id');

        $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur4-deact-c',
        ])->assertOk();

        $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/reactivate', [], [
            'X-Idempotency-Key' => 'cur4-re-c',
        ])->assertOk()->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curricula'), [
            'id' => $curriculumId,
            'status' => 1,
        ]);
    }
}
