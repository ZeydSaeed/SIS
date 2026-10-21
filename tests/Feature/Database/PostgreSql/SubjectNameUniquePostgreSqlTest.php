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

/**
 * One spelling per subject (sis:audit-data-quality «duplicate subject name»): a second subject with the name of
 * another — any case, surrounding spaces — is refused on create and on rename; found by the e2e suite.
 */
final class SubjectNameUniquePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function a_subject_name_already_used_is_refused_on_create_and_on_rename(): void
    {
        $schoolId = $this->createSchool('SCH-SNU', 'Subject names');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $this->postJson('/api/v1/curriculum/subjects', ['code' => 'CHEM-SNU', 'name' => 'Chemistry SNU', 'subject_type' => 1], ['X-Idempotency-Key' => 'snu-1'])
            ->assertCreated();
        $this->postJson('/api/v1/curriculum/subjects', ['code' => 'CHEM-SNU-2', 'name' => '  chemistry snu ', 'subject_type' => 1], ['X-Idempotency-Key' => 'snu-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.subject_name_taken');
        $this->assertSame(0, DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->where('code', 'CHEM-SNU-2')->count());

        $physics = (int) $this->postJson('/api/v1/curriculum/subjects', ['code' => 'PHY-SNU', 'name' => 'Physics SNU', 'subject_type' => 1], ['X-Idempotency-Key' => 'snu-3'])
            ->assertCreated()->json('data.subject_id');
        $this->patchJson('/api/v1/curriculum/subjects/'.$physics, ['name' => 'Chemistry SNU'], ['X-Idempotency-Key' => 'snu-4'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.subject_name_taken');
        // Its own name (other fields changed) and a new name are fine.
        $this->patchJson('/api/v1/curriculum/subjects/'.$physics, ['name' => 'Physics SNU', 'pass_grade' => 55], ['X-Idempotency-Key' => 'snu-5'])->assertOk();
        $this->patchJson('/api/v1/curriculum/subjects/'.$physics, ['name' => 'Applied Physics SNU'], ['X-Idempotency-Key' => 'snu-6'])->assertOk();
    }
}
