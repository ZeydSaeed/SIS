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

/** «نوع المادة» — «نظري» (4) next to أساسي (1) · اختصاص (2) · عملي (3). */
final class CurriculumTheorySubjectTypePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function a_subject_can_be_created_and_switched_to_theory_but_not_to_an_unknown_type(): void
    {
        $schoolId = $this->createSchool('SCH-THY', 'Theory');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
        $table = SchemaHelper::qualified('curriculum', 'subjects');

        $theoryId = (int) $this->postJson('/api/v1/curriculum/subjects', ['code' => 'THY-1', 'name' => 'Theory 1', 'subject_type' => 4], ['X-Idempotency-Key' => 'thy-1'])
            ->assertCreated()->json('data.subject_id');
        $this->assertSame(4, (int) DB::table($table)->where('id', $theoryId)->value('subject_type'));

        $this->postJson('/api/v1/curriculum/subjects', ['code' => 'THY-5', 'name' => 'Unknown', 'subject_type' => 5], ['X-Idempotency-Key' => 'thy-5'])
            ->assertStatus(422);
        $this->assertFalse(DB::table($table)->where('code', 'THY-5')->exists());
    }
}
