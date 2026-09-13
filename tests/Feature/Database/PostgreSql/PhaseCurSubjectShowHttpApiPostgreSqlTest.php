<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurSubjectShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_subject(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-S1', 'CUR Show Subject');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $subjectId = (int) $this->postJson('/api/v1/curriculum/subjects', [
            'code' => 'SHOW-CUR1',
            'name' => 'Show Subject',
            'name_en' => 'Show Subject',
            'subject_type' => 1,
            'credit_hours' => 3,
            'max_grade' => 100,
            'pass_grade' => 50,
        ], ['X-Idempotency-Key' => 'cur-show-sub'])->json('data.subject_id');

        $this->getJson('/api/v1/curriculum/subjects/'.$subjectId)
            ->assertOk()
            ->assertJsonPath('data.id', $subjectId)
            ->assertJsonPath('data.code', 'SHOW-CUR1')
            ->assertJsonPath('data.name', 'Show Subject')
            ->assertJsonPath('data.subject_type', 1)
            ->assertJsonPath('data.credit_hours', 3);
    }
}
