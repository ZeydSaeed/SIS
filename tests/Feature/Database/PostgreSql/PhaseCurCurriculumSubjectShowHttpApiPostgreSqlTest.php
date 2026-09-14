<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurCurriculumSubjectShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_curriculum_subject_link_any_status(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-U15', 'CUR Show Link');
        $yearId = $this->createAcademicYear('AY-CUR-U15');
        $gradeId = $this->createGradeLevel('G-CUR-U15');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $subjectId = (int) $this->postJson('/api/v1/curriculum/subjects', [
            'code' => 'CHEM-U15',
            'name' => 'Chemistry',
            'subject_type' => 1,
        ], ['X-Idempotency-Key' => 'cur-u15-subj'])->json('data.subject_id');

        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'CUR U15 Curriculum',
        ], ['X-Idempotency-Key' => 'cur-u15-cur'])->json('data.curriculum_id');

        $linkId = (int) $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects', [
            'subject_id' => $subjectId,
            'weekly_hours' => 4,
            'is_required' => true,
            'subject_order' => 1,
        ], ['X-Idempotency-Key' => 'cur-u15-link'])->json('data.link_id');

        $this->getJson('/api/v1/curriculum/curriculum-subjects/'.$linkId)
            ->assertOk()
            ->assertJsonPath('data.id', $linkId)
            ->assertJsonPath('data.curriculum_id', $curriculumId)
            ->assertJsonPath('data.subject_id', $subjectId)
            ->assertJsonPath('data.status', 1);

        $this->postJson('/api/v1/curriculum/curriculum-subjects/'.$linkId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur-u15-off',
        ])->assertOk();

        $this->getJson('/api/v1/curriculum/curriculum-subjects/'.$linkId)
            ->assertOk()
            ->assertJsonPath('data.id', $linkId)
            ->assertJsonPath('data.status', 2);
    }
}
