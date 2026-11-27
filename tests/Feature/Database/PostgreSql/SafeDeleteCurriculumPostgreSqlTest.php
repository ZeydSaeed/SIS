<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «حذف آمن» — subjects, curricula and their subject lines. */
final class SafeDeleteCurriculumPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function a_used_subject_is_refused_and_a_curriculum_goes_with_its_lines(): void
    {
        $schoolId = $this->createSchool('SCH-SDC', 'Curriculum delete');
        $yearId = $this->createAcademicYear('AY-SDC');
        $gradeId = $this->createGradeLevel('G-SDC');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
        $api = fn (string $uri, array $body, string $key) => $this->postJson($uri, $body, ['X-Idempotency-Key' => $key])->assertCreated();

        $used = (int) $api('/api/v1/curriculum/subjects', ['code' => 'SDC-1', 'name' => 'Used', 'subject_type' => 1], 'sdc-s1')->json('data.subject_id');
        $free = (int) $api('/api/v1/curriculum/subjects', ['code' => 'SDC-2', 'name' => 'Free', 'subject_type' => 4], 'sdc-s2')->json('data.subject_id');
        $plan = (int) $api('/api/v1/curriculum/curricula', ['academic_year_id' => $yearId, 'grade_level_id' => $gradeId, 'name' => 'SDC'], 'sdc-c')->json('data.curriculum_id');
        $api('/api/v1/curriculum/curricula/'.$plan.'/subjects', ['subject_id' => $used, 'weekly_hours' => 2, 'is_required' => true, 'subject_order' => 1], 'sdc-l');

        // A subject nobody uses is deleted for real.
        $this->from('/curriculum')->post('/curriculum/subjects/'.$free.'/delete')->assertSessionHas('success', 'flash.curriculum.subjectDeleted');
        $this->assertFalse(DB::table('curriculum.subjects')->where('id', $free)->exists());

        // A subject in a curriculum is refused, naming the curriculum.
        $this->from('/curriculum')->post('/curriculum/subjects/'.$used.'/delete')
            ->assertSessionHasErrors(['delete' => 'delete.linked?with='.rawurlencode('المناهج')]);

        // Removed from the curriculum (by the pair), it can then be deleted.
        $this->from('/curriculum')->post('/curriculum/curricula/'.$plan.'/subjects/'.$used.'/delete')
            ->assertSessionHas('success', 'flash.curriculum.subjectLinkDeleted');
        $this->from('/curriculum')->post('/curriculum/subjects/'.$used.'/delete')->assertSessionHas('success', 'flash.curriculum.subjectDeleted');

        // The curriculum goes (with any lines left).
        $this->from('/curriculum')->post('/curriculum/curricula/'.$plan.'/delete')->assertSessionHas('success', 'flash.curriculum.curriculumDeleted');
        $this->assertFalse(DB::table('curriculum.curricula')->where('id', $plan)->exists());

        // Another school's curriculum is not found from this school.
        $other = $this->createSchool('SCH-SDC2', 'Other');
        $foreign = (int) DB::table('curriculum.curricula')->insertGetId(['school_id' => $other, 'academic_year_id' => $yearId, 'grade_level_id' => $gradeId, 'name' => 'Foreign', 'status' => 1]);
        $this->from('/curriculum')->post('/curriculum/curricula/'.$foreign.'/delete')->assertSessionHasErrors(['delete' => 'delete.not_found']);
        $this->assertTrue(DB::table('curriculum.curricula')->where('id', $foreign)->exists());
    }
}
