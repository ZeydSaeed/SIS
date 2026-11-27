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

/** A subject in a curriculum carries «نظري» + «عملي» hours; weekly hours (read by the timetable) is their sum. */
final class CurriculumSubjectHoursSplitPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function linking_with_a_split_stores_both_and_the_sum_and_the_hours_can_be_changed(): void
    {
        $schoolId = $this->createSchool('SCH-HRS', 'Hours');
        $yearId = $this->createAcademicYear('AY-HRS');
        $gradeId = $this->createGradeLevel('G-HRS');
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantCurriculumManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
        $links = SchemaHelper::qualified('curriculum', 'curriculum_subjects');

        $subjectId = (int) $this->postJson('/api/v1/curriculum/subjects', ['code' => 'OS-HRS', 'name' => 'OS Security', 'subject_type' => 2], ['X-Idempotency-Key' => 'hrs-s'])
            ->assertCreated()->json('data.subject_id');
        $curriculumId = (int) $this->postJson('/api/v1/curriculum/curricula', ['academic_year_id' => $yearId, 'grade_level_id' => $gradeId, 'name' => 'HRS'], ['X-Idempotency-Key' => 'hrs-c'])
            ->assertCreated()->json('data.curriculum_id');

        // Web page: link with 2 نظري + 4 عملي → weekly 6.
        $this->from('/curriculum')->withHeader('X-Idempotency-Key', 'hrs-l')
            ->post('/curriculum/curricula/'.$curriculumId.'/subjects', ['subject_id' => $subjectId, 'theory_hours' => 2, 'practical_hours' => 4, 'is_required' => true])
            ->assertSessionHas('success', 'flash.curriculum.subjectLinked');
        $row = DB::table($links)->where('curriculum_id', $curriculumId)->first();
        $this->assertSame([2, 4, 6], [(int) $row->theory_hours, (int) $row->practical_hours, (int) $row->weekly_hours]);

        // «الساعات»: 3 نظري, no عملي → weekly 3.
        $this->from('/curriculum')->withHeader('X-Idempotency-Key', 'hrs-u')
            ->patch('/curriculum/curriculum-subjects/'.$row->id.'/hours', ['theory_hours' => 3, 'practical_hours' => null])
            ->assertSessionHas('success', 'flash.curriculum.subjectHoursUpdated');
        $row = DB::table($links)->where('id', $row->id)->first();
        $this->assertSame([3, null, 3], [(int) $row->theory_hours, $row->practical_hours, (int) $row->weekly_hours]);

        // Over 40 in total is refused.
        $this->from('/curriculum')->withHeader('X-Idempotency-Key', 'hrs-x')
            ->patch('/curriculum/curriculum-subjects/'.$row->id.'/hours', ['theory_hours' => 30, 'practical_hours' => 20])
            ->assertSessionHasErrors('link');
        $this->assertSame(3, (int) DB::table($links)->where('id', $row->id)->value('weekly_hours'));
    }
}
