<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurCurriculumShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_curriculum(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-U13', 'CUR Show Curriculum');
        $yearId = $this->createAcademicYear('AY-CUR-U13');
        $gradeId = $this->createGradeLevel('G-CUR-U13');

        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', [
            'academic_year_id' => $yearId,
            'grade_level_id' => $gradeId,
            'name' => 'Show Curriculum U13',
        ], ['X-Idempotency-Key' => 'cur-show-u13'])->json('data.curriculum_id');

        $this->getJson('/api/v1/curriculum/curricula/'.$curriculumId)
            ->assertOk()
            ->assertJsonPath('data.id', $curriculumId)
            ->assertJsonPath('data.name', 'Show Curriculum U13')
            ->assertJsonPath('data.academic_year_id', $yearId)
            ->assertJsonPath('data.grade_level_id', $gradeId)
            ->assertJsonPath('data.status', 1);
    }
}
