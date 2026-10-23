<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\GradeStatus;
use App\Infrastructure\Persistence\Eloquent\SecurityAuditLogRecord;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U12 — term-results sheet: calculate / finalize the term result of a subject for every seated student of a
 * class through the single-student commands; finalize never re-finalizes an official result.
 */
final class TermResultSheetWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @return array<string, mixed> */
    private function scene(int $weight = 100): array
    {
        $school = $this->createSchool('SCH-TRS', 'Term results');
        $g = $this->seedExamGradeGraph($school, suffix: 'TRS');
        $this->markSessionInProgressForGradeEntry($g['session_id']);
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $g['exam_id'])->value('exam_type_id');
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('id', $typeId)->update(['weight_percentage' => $weight]);
        $class = (int) $this->createClassForSchool($school, $g['year_id'])->id;
        $section = (int) $this->createSectionForClass($class)->id;

        $student = function (?string $score, int $status) use ($school, $g, $class, $section): int {
            $e = $this->createActiveEnrollmentForSchool($school, $g['year_id']);
            DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $e->id)->update(['class_id' => $class, 'section_id' => $section]);
            $seat = (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->insertGetId([
                'exam_session_id' => $g['session_id'], 'school_id' => $school, 'enrollment_id' => $e->id, 'status' => 1, 'created_at' => now(),
            ]);
            if ($score !== null) {
                DB::table(SchemaHelper::qualified('exams', 'student_grades'))->insert([
                    'academic_year_id' => $g['year_id'], 'school_id' => $school, 'exam_enrollment_id' => $seat, 'exam_session_id' => $g['session_id'],
                    'enrollment_id' => $e->id, 'student_id' => $e->student_id, 'subject_id' => $g['subject_id'], 'score' => $score, 'max_score' => 100,
                    'is_absent' => false, 'status' => $status, 'is_current' => true, 'entered_at' => now(),
                    'finalized_at' => $status === GradeStatus::Finalized->value ? now() : null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return (int) $e->id;
        };

        return $g + [
            'school' => $school, 'class' => $class, 'section' => $section,
            'final' => $student('80', GradeStatus::Finalized->value),
            'entered' => $student('60', GradeStatus::Entered->value),
            'ungraded' => $student(null, 0),
        ];
    }

    private function as(string $role, int $school): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->{'grant'.$role}($user, $school);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        return $user;
    }

    /** @param  array<string, mixed>  $s  @return array<string, mixed> */
    private function payload(array $s, array $overrides = []): array
    {
        return $overrides + ['academic_year_id' => $s['year_id'], 'term_id' => $s['term_id'], 'subject_id' => $s['subject_id'], 'class_id' => $s['class']];
    }

    /** @param  array<string, mixed>  $s */
    private function submit(array $s, string $mode, string $key, array $overrides = []): TestResponse
    {
        return $this->from('/results/sheet?academic_year_id='.$s['year_id'])->post("/results/sheet/{$mode}", $this->payload($s, $overrides), ['X-Idempotency-Key' => $key]);
    }

    /** @return array<int, string> enrollment → status */
    private function outcomes(TestResponse $response): array
    {
        return array_column($response->getSession()->get('term_results_outcome')['outcomes'] ?? [], 'status', 'enrollment_id');
    }

    private function results(int $enrollment, string $flag): int
    {
        return DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $enrollment)->where($flag, true)->count();
    }

    #[Test]
    public function calculate_produces_an_operational_result_per_graded_student(): void
    {
        $s = $this->scene();
        $user = $this->as('ResultsManager', $s['school']);

        $response = $this->submit($s, 'calculate', 'trs-calc');

        $this->assertSame([
            $s['final'] => 'calculated',
            $s['entered'] => 'calculated',
            $s['ungraded'] => 'results.term_no_eligible_grades',
        ], $this->outcomes($response));
        $response->assertSessionHas('error', 'flash.results.batch.partial');
        $this->assertSame('80.00', (string) DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->where('is_current_operational', true)->value('weighted_total'));
        $this->assertSame(0, $this->results($s['final'], 'is_current_official'));
        $this->assertTrue(SecurityAuditLogRecord::query()->where('action', 'results.web.term_batch.calculate')->where('actor_id', $user->id)->exists());
    }

    #[Test]
    public function finalize_needs_finalized_grades_and_never_refinalizes(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);

        $first = $this->submit($s, 'finalize', 'trs-final-1');
        $this->assertSame([
            $s['final'] => 'finalized',
            $s['entered'] => 'results.official_dataset_incomplete',
            $s['ungraded'] => 'results.official_dataset_incomplete',
        ], $this->outcomes($first));
        $this->assertSame(1, $this->results($s['final'], 'is_current_official'));
        $versions = DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->count();

        $again = $this->submit($s, 'finalize', 'trs-final-2');
        $this->assertSame('already_official', $this->outcomes($again)[$s['final']]);
        $this->assertSame($versions, DB::table(SchemaHelper::qualified('results', 'term_results'))->where('enrollment_id', $s['final'])->count(), 'no new official version');
    }

    #[Test]
    public function repeating_a_request_replays(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $this->submit($s, 'calculate', 'trs-repeat');
        $versions = DB::table(SchemaHelper::qualified('results', 'term_results'))->whereIn('enrollment_id', [$s['final'], $s['entered']])->count();

        $again = $this->submit($s, 'calculate', 'trs-repeat');

        $this->assertSame('replayed', $this->outcomes($again)[$s['final']]);
        $this->assertSame($versions, DB::table(SchemaHelper::qualified('results', 'term_results'))->whereIn('enrollment_id', [$s['final'], $s['entered']])->count());
    }

    #[Test]
    public function a_weight_sum_other_than_100_is_reported_per_student(): void
    {
        $s = $this->scene(weight: 40);
        $this->as('ResultsManager', $s['school']);

        $this->assertSame('results.term_weight_sum_invalid', $this->outcomes($this->submit($s, 'calculate', 'trs-weight'))[$s['final']]);
        $this->assertSame(0, $this->results($s['final'], 'is_current_operational'));
    }

    #[Test]
    public function invalid_targets_are_refused_and_process_nobody(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $otherYear = $this->createAcademicYear('AY-TRS-OTHER');
        $otherTerm = (int) DB::table(SchemaHelper::qualified('academic', 'terms'))->insertGetId([
            'academic_year_id' => $otherYear, 'code' => 'T-TRS-X', 'name' => 'Other term', 'start_date' => '2027-09-01', 'end_date' => '2027-12-31',
            'term_order' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherClass = (int) $this->createClassForSchool($s['school'], $otherYear)->id;

        $this->submit($s, 'calculate', 'trs-t', ['term_id' => $otherTerm])->assertSessionHas('error', 'results.batch.term_invalid');
        $this->submit($s, 'calculate', 'trs-c', ['class_id' => $otherClass])->assertSessionHas('error', 'results.batch.class_invalid');
        $this->submit($s, 'calculate', 'trs-s', ['section_id' => (int) $this->createSectionForClass($otherClass)->id])->assertSessionHas('error', 'results.batch.section_invalid');
        $this->submit($s, 'calculate', 'trs-e', ['enrollment_ids' => [$s['final']]])->assertSessionHasErrors('enrollment_ids');

        $this->assertSame(0, DB::table(SchemaHelper::qualified('results', 'term_results'))->where('school_id', $s['school'])->count());
    }

    #[Test]
    public function only_results_managers_may_calculate_or_finalize(): void
    {
        $s = $this->scene();
        $this->as('ResultsViewer', $s['school']);
        $this->get('/results/sheet?academic_year_id='.$s['year_id'])->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('abilities.calculate', false)->where('abilities.finalize', false));
        $this->submit($s, 'calculate', 'trs-v1')->assertForbidden();
        $this->submit($s, 'finalize', 'trs-v2')->assertForbidden();

        $this->as('GradesTeacher', $s['school']);
        $this->get('/results/sheet?academic_year_id='.$s['year_id'])->assertForbidden();
        $this->submit($s, 'calculate', 'trs-t1')->assertForbidden();

        $this->assertSame(0, DB::table(SchemaHelper::qualified('results', 'term_results'))->where('school_id', $s['school'])->count());
    }

    #[Test]
    public function the_sheet_lists_the_seated_students_with_their_results(): void
    {
        $s = $this->scene();
        $this->as('ResultsManager', $s['school']);
        $this->submit($s, 'calculate', 'trs-page');

        $this->get("/results/sheet?academic_year_id={$s['year_id']}&term_id={$s['term_id']}&subject_id={$s['subject_id']}&class_id={$s['class']}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('results/sheet')
                ->where('abilities.calculate', true)->where('abilities.finalize', true)
                ->where('subjects', fn ($subjects) => collect($subjects)->pluck('id')->contains($s['subject_id']))
                ->has('rows', 3)
                ->where('rows', fn ($rows) => collect($rows)->firstWhere('enrollment_id', $s['final'])['operational']['weighted_total'] === '80.00'
                    && collect($rows)->firstWhere('enrollment_id', $s['final'])['finalized_grades'] === 1
                    && collect($rows)->firstWhere('enrollment_id', $s['ungraded'])['operational'] === null));
    }
}
