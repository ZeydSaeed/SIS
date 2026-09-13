<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurCurriculaHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_create_curriculum_link_subject_and_deactivate(): void
    {
        $schoolId = $this->createSchool('SCH-CUR3', 'Curricula School');
        $yearId = $this->createAcademicYear('AY-CUR3');
        $gradeId = $this->createGradeLevel('G-CUR3');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $subjectId = (int) $this->postJson('/api/v1/curriculum/subjects', [
            'code' => 'BIO-CUR3',
            'name' => 'Biology',
            'subject_type' => 1,
        ], ['X-Idempotency-Key' => 'cur3-subj'])->assertCreated()->json('data.subject_id');

        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'Grade Curriculum CUR3',
        ], ['X-Idempotency-Key' => 'cur3-cur'])
            ->assertCreated()
            ->json('data.curriculum_id');

        $this->getJson('/api/v1/curriculum/curricula?academic_year_id='.$yearId)
            ->assertOk()
            ->assertJsonFragment(['id' => $curriculumId, 'name' => 'Grade Curriculum CUR3']);

        $linkId = (int) $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects', [
            'subject_id' => $subjectId,
            'weekly_hours' => 5,
            'is_required' => true,
            'subject_order' => 1,
        ], ['X-Idempotency-Key' => 'cur3-link'])
            ->assertCreated()
            ->json('data.link_id');

        $this->getJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.subject_id', $subjectId);

        $this->postJson('/api/v1/curriculum/curriculum-subjects/'.$linkId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur3-unlink',
        ])->assertOk()->assertJsonPath('data.status', 2);

        $this->getJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur3-deact-cur',
        ])->assertOk()->assertJsonPath('data.status', 2);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curricula'), [
            'id' => $curriculumId,
            'status' => 2,
        ]);
        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curriculum_subjects'), [
            'id' => $linkId,
            'status' => 2,
        ]);
    }
}
