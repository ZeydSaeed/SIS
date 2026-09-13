<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurPrerequisiteReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_subject_prerequisite(): void
    {
        $schoolId = $this->createSchool('SCH-CUR11', 'CUR-U11 School');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $mathId = $this->createSubject('MATH-CUR11', 'Math');
        $algId = $this->createSubject('ALG-CUR11', 'Algebra');

        $prereqId = (int) $this->postJson('/api/v1/curriculum/subjects/'.$algId.'/prerequisites', [
            'prerequisite_subject_id' => $mathId,
        ], ['X-Idempotency-Key' => 'cur11-add'])->assertCreated()->json('data.prerequisite_id');

        $this->postJson('/api/v1/curriculum/prerequisites/'.$prereqId.'/deactivate', [], [
            'X-Idempotency-Key' => 'cur11-off',
        ])->assertOk();

        $this->postJson('/api/v1/curriculum/prerequisites/'.$prereqId.'/reactivate', [], [
            'X-Idempotency-Key' => 'cur11-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 1);

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'prerequisites'), [
            'id' => $prereqId,
            'status' => 1,
        ]);
    }

    private function createSubject(string $code, string $name): int
    {
        return (int) $this->postJson('/api/v1/curriculum/subjects', [
            'code' => $code,
            'name' => $name,
            'subject_type' => 1,
        ], ['X-Idempotency-Key' => 'cur11-'.$code])->assertCreated()->json('data.subject_id');
    }
}
