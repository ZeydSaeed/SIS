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

final class PhaseCurPrerequisitesHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_add_list_deactivate_and_reject_cycle(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-1', 'Curriculum School');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $mathId = $this->createSubject('MATH-CUR', 'Math');
        $algId = $this->createSubject('ALG-CUR', 'Algebra');
        $calcId = $this->createSubject('CALC-CUR', 'Calculus');

        $prereqId = (int) $this->postJson('/api/v1/curriculum/subjects/'.$algId.'/prerequisites', [
            'prerequisite_subject_id' => $mathId,
        ], ['X-Idempotency-Key' => 'cur-add-1'])
            ->assertCreated()
            ->assertJsonPath('data.subject_id', $algId)
            ->json('data.prerequisite_id');

        $this->postJson('/api/v1/curriculum/subjects/'.$algId.'/prerequisites', [
            'prerequisite_subject_id' => $mathId,
        ], ['X-Idempotency-Key' => 'cur-add-1'])
            ->assertOk()
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/curriculum/subjects/'.$algId.'/prerequisites')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.prerequisite_subject_id', $mathId);

        $this->postJson('/api/v1/curriculum/subjects/'.$calcId.'/prerequisites', [
            'prerequisite_subject_id' => $algId,
        ], ['X-Idempotency-Key' => 'cur-add-2'])->assertCreated();

        $this->postJson('/api/v1/curriculum/subjects/'.$mathId.'/prerequisites', [
            'prerequisite_subject_id' => $calcId,
        ], ['X-Idempotency-Key' => 'cur-cycle'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.prerequisite_cycle');

        $this->postJson('/api/v1/curriculum/subjects/'.$mathId.'/prerequisites', [
            'prerequisite_subject_id' => $mathId,
        ], ['X-Idempotency-Key' => 'cur-self'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.prerequisite_self');

        $this->postJson('/api/v1/curriculum/prerequisites/'.$prereqId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur-deact',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 2);

        $this->getJson('/api/v1/curriculum/subjects/'.$algId.'/prerequisites')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'prerequisites'), [
            'id' => $prereqId,
            'status' => 2,
        ]);
    }

    private function createSubject(string $code, string $name): int
    {
        return (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code,
            'name' => $name,
            'name_en' => $name,
            'subject_type' => 1,
            'credit_hours' => 3,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
