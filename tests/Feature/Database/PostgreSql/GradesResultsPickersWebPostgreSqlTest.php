<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U11 — the grades and results pages choose a session / a student instead of asking for raw ids, and show names;
 * every choice list and name stays inside the school in context.
 */
final class GradesResultsPickersWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @param  list<string>  $roles */
    private function as(array $roles, int $school): User
    {
        $user = User::factory()->create();
        foreach ($roles as $role) {
            app(SecurityPermissionSeeder::class)->{'grant'.$role}($user, $school);
        }
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        return $user;
    }

    /** @return array<string, mixed> */
    private function scene(): array
    {
        $school = $this->createSchool('SCH-PICK', 'Pickers');
        $g = $this->seedExamGradeGraph($school, suffix: 'PICK');
        DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('id', '<>', $g['year_id'])->update(['is_current' => false]);
        DB::table(SchemaHelper::qualified('academic', 'academic_years'))->where('id', $g['year_id'])->update(['is_current' => true]);
        $foreignSchool = $this->createSchool('SCH-PICK-F', 'Foreign');
        $f = $this->seedExamGradeGraph($foreignSchool, $g['year_id'], 'PICKF');
        $student = DB::table(SchemaHelper::qualified('students', 'students'))->where('id', $g['student_id'])->first(['full_name', 'student_code']);

        return $g + ['school' => $school, 'foreign' => $f, 'name' => (string) $student->full_name, 'code' => (string) $student->student_code];
    }

    #[Test]
    public function the_grades_page_lists_the_schools_sessions_and_student_names(): void
    {
        $s = $this->scene();
        $this->markSessionInProgressForGradeEntry($s['session_id']);
        $this->as(['GradesManager'], $s['school']);
        $this->post("/grades/sheet/{$s['session_id']}", ['rows' => [['exam_enrollment_id' => $s['exam_enrollment_id'], 'score' => '77', 'is_absent' => false]]],
            ['X-Idempotency-Key' => 'pick-grade']);

        $this->get("/grades?academic_year_id={$s['year_id']}&session_id={$s['session_id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('grades/index')
            ->where('sessions', fn ($rows) => collect($rows)->pluck('id')->all() === [$s['session_id']])
            ->where("students.{$s['enrollment_id']}.student_name", $s['name']));
    }

    #[Test]
    public function the_results_picker_finds_only_the_schools_students(): void
    {
        $s = $this->scene();
        $this->as(['ResultsViewer'], $s['school']);

        $this->get('/results?academic_year_id='.$s['year_id'].'&q='.urlencode($s['code']))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('results/index')
            ->where('picker.candidates', fn ($rows) => collect($rows)->pluck('enrollment_id')->all() === [$s['enrollment_id']])
            ->where('picker.student', null));

        // No search, no student chosen: the year's students of this school are listed to choose from.
        $this->get('/results?academic_year_id='.$s['year_id'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('picker.candidates', fn ($rows) => collect($rows)->pluck('enrollment_id')->contains($s['enrollment_id'])
                && ! collect($rows)->pluck('enrollment_id')->contains($s['foreign']['enrollment_id'])));

        $foreignCode = (string) DB::table(SchemaHelper::qualified('students', 'students'))->where('id', $s['foreign']['student_id'])->value('student_code');
        $this->get('/results?academic_year_id='.$s['year_id'].'&q='.urlencode($foreignCode))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('picker.candidates', []));

        $this->get("/results/show?academic_year_id={$s['year_id']}&enrollment_id={$s['enrollment_id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('results/show')
            ->where('picker.student.student_name', $s['name']));

        // Another school's enrollment is not found, on every results page.
        foreach (['/results', '/results/show', '/results/transcript'] as $path) {
            $this->get("{$path}?academic_year_id={$s['year_id']}&enrollment_id={$s['foreign']['enrollment_id']}")->assertNotFound();
        }
    }
}
