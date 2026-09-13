<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurSubjectsHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_create_list_and_deactivate_subject(): void
    {
        $schoolId = $this->createSchool('SCH-CUR2', 'Curriculum Subjects');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $subjectId = (int) $this->postJson('/api/v1/curriculum/subjects', [
            'code' => 'PHY-CUR2',
            'name' => 'Physics',
            'name_en' => 'Physics',
            'subject_type' => 1,
            'credit_hours' => 4,
            'max_grade' => 100,
            'pass_grade' => 50,
        ], ['X-Idempotency-Key' => 'cur2-create'])
            ->assertCreated()
            ->json('data.subject_id');

        $this->postJson('/api/v1/curriculum/subjects', [
            'code' => 'PHY-CUR2',
            'name' => 'Physics',
            'subject_type' => 1,
        ], ['X-Idempotency-Key' => 'cur2-create'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->postJson('/api/v1/curriculum/subjects', [
            'code' => 'PHY-CUR2',
            'name' => 'Physics Dup',
            'subject_type' => 1,
        ], ['X-Idempotency-Key' => 'cur2-dup'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.subject_code_taken');

        $this->getJson('/api/v1/curriculum/subjects')
            ->assertOk()
            ->assertJsonFragment(['code' => 'PHY-CUR2', 'id' => $subjectId]);

        $this->postJson('/api/v1/curriculum/subjects/'.$subjectId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur2-deact',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 2);

        $this->getJson('/api/v1/curriculum/subjects')
            ->assertOk()
            ->assertJsonMissing(['code' => 'PHY-CUR2']);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'subjects'), [
            'id' => $subjectId,
            'status' => 2,
        ]);
    }
}
