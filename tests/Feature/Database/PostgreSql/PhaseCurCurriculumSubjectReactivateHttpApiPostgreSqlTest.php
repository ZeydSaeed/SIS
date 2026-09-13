<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurCurriculumSubjectReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_curriculum_subject_link(): void
    {
        $schoolId = $this->createSchool('SCH-CUR10', 'CUR-U10 School');
        $yearId = $this->createAcademicYear('AY-CUR10');
        $gradeId = $this->createGradeLevel('G-CUR10');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $subjectId = (int) $this->postJson('/api/v1/curriculum/subjects', [
            'code' => 'BIO-CUR10',
            'name' => 'Biology',
            'subject_type' => 1,
        ], ['X-Idempotency-Key' => 'cur10-subj'])->assertCreated()->json('data.subject_id');

        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'CUR10 Curriculum',
        ], ['X-Idempotency-Key' => 'cur10-cur'])->assertCreated()->json('data.curriculum_id');

        $linkId = (int) $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects', [
            'subject_id' => $subjectId,
            'weekly_hours' => 3,
            'is_required' => true,
            'subject_order' => 1,
        ], ['X-Idempotency-Key' => 'cur10-link'])->assertCreated()->json('data.link_id');

        $this->postJson('/api/v1/curriculum/curriculum-subjects/'.$linkId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur10-off',
        ])->assertOk();

        $this->postJson('/api/v1/curriculum/curriculum-subjects/'.$linkId.'/reactivate', [], [
            'X-Idempotency-Key' => 'cur10-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curriculum_subjects'), [
            'id' => $linkId,
            'status' => 1,
        ]);
    }
}
