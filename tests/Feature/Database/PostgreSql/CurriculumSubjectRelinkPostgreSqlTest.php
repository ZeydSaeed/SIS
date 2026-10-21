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
 * Linking a subject already active in a curriculum answered «done» while silently keeping the old weekly hours (found
 * by the e2e suite). It is refused now; an inactive link is still brought back by linking it.
 */
final class CurriculumSubjectRelinkPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function linking_an_active_subject_again_is_refused_and_an_inactive_one_is_reactivated(): void
    {
        $schoolId = $this->createSchool('SCH-RLK', 'Relink');
        $yearId = $this->createAcademicYear('AY-RLK');
        $gradeId = $this->createGradeLevel('G-RLK');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);

        $subjectId = (int) $this->postJson('/api/v1/curriculum/subjects', ['code' => 'BIO-RLK', 'name' => 'Biology RLK', 'subject_type' => 1], ['X-Idempotency-Key' => 'rlk-s'])
            ->assertCreated()->json('data.subject_id');
        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', ['academic_year_id' => $yearId, 'grade_level_id' => $gradeId, 'name' => 'RLK'], ['X-Idempotency-Key' => 'rlk-c'])
            ->assertCreated()->json('data.curriculum_id');
        $link = ['subject_id' => $subjectId, 'weekly_hours' => 3, 'is_required' => true, 'subject_order' => 1];
        $linkId = (int) $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects', $link, ['X-Idempotency-Key' => 'rlk-1'])
            ->assertCreated()->json('data.link_id');

        $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects', ['weekly_hours' => 2] + $link, ['X-Idempotency-Key' => 'rlk-2'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'curriculum.subject_already_linked');
        $this->assertSame(3, (int) DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->where('id', $linkId)->value('weekly_hours'));

        $this->postJson('/api/v1/curriculum/curriculum-subjects/'.$linkId.'/deactivate', [], ['X-Idempotency-Key' => 'rlk-off'])->assertOk();
        $this->assertSame($linkId, (int) $this->postJson('/api/v1/curriculum/curricula/'.$curriculumId.'/subjects', ['weekly_hours' => 4] + $link, ['X-Idempotency-Key' => 'rlk-3'])
            ->assertSuccessful()->json('data.link_id'));
        $this->assertSame(4, (int) DB::table(SchemaHelper::qualified('curriculum', 'curriculum_subjects'))->where('id', $linkId)->value('weekly_hours'));
    }
}
