<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseCurPrerequisiteShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_prerequisite(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-U14', 'CUR Show Prereq');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $mathId = $this->createSubject('MATH-U14', 'Math U14');
        $algId = $this->createSubject('ALG-U14', 'Algebra U14');

        $prereqId = (int) $this->postJson('/api/v1/curriculum/subjects/'.$algId.'/prerequisites', [
            'prerequisite_subject_id' => $mathId,
        ], ['X-Idempotency-Key' => 'cur-show-prereq'])->json('data.prerequisite_id');

        $this->getJson('/api/v1/curriculum/prerequisites/'.$prereqId)
            ->assertOk()
            ->assertJsonPath('data.id', $prereqId)
            ->assertJsonPath('data.subject_id', $algId)
            ->assertJsonPath('data.prerequisite_subject_id', $mathId)
            ->assertJsonPath('data.status', 1);
    }

    private function createSubject(string $code, string $name): int
    {
        return (int) DB::table(\App\Database\SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
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
