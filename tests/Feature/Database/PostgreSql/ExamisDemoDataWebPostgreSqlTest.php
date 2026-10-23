<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS U15 — a synthetic school year driven end to end through the web: two exam types (40 / 60), a midterm and a
 * final of one subject, four students of one class; grades through the entry sheet, then term → annual → GPA →
 * ranking → transcripts; and the exam / session edit and cancel actions with their guards. Synthetic data only.
 */
final class ExamisDemoDataWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** Scores (midterm, final) out of 100; null = absent; a student without an entry is never graded. */
    private const SCORES = [
        'sara' => ['80', '90'],
        'ali' => ['45', '50'],
        'maryam' => [null, '70'],
    ];

    /** @return array<string, mixed> */
    private function demo(): array
    {
        $school = $this->createSchool('SCH-DEMO', 'مدرسة تجريبية');
        $g = $this->seedExamGradeGraph($school, suffix: 'DEMO');
        $t = static fn (string $table): string => SchemaHelper::qualified('exams', $table);
        DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->where('id', $g['subject_id'])->update(['name' => 'الرياضيات']);
        $midType = (int) DB::table($t('exams'))->where('id', $g['exam_id'])->value('exam_type_id');
        DB::table($t('exams'))->where('id', $g['exam_id'])->update(['name' => 'امتحان نصف الفصل']);
        $finalType = (int) DB::table($t('exam_types'))->insertGetId(['school_id' => $school, 'code' => 'FINAL-DEMO', 'name' => 'نهائي', 'weight_percentage' => 60]);
        $finalExam = (int) DB::table($t('exams'))->insertGetId([
            'academic_year_id' => $g['year_id'], 'school_id' => $school, 'term_id' => $g['term_id'], 'exam_type_id' => $finalType,
            'name' => 'الامتحان النهائي', 'start_date' => '2026-12-01', 'end_date' => '2026-12-15', 'status' => ExamStatus::Scheduled->value,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $finalSession = (int) DB::table($t('exam_sessions'))->insertGetId([
            'exam_id' => $finalExam, 'school_id' => $school, 'subject_id' => $g['subject_id'], 'session_date' => '2026-12-05',
            'start_time' => '09:00:00', 'end_time' => '11:00:00', 'max_grade' => 100, 'pass_grade' => 50,
            'status' => ExamSessionStatus::Scheduled->value, 'created_at' => now(),
        ]);
        $class = (int) $this->createClassForSchool($school, $g['year_id'])->id;
        $section = (int) $this->createSectionForClass($class)->id;

        $students = [];
        $seats = [];
        foreach (['sara' => 'سارة أحمد', 'ali' => 'علي حسين', 'maryam' => 'مريم كاظم', 'hasan' => 'حسن جاسم'] as $key => $name) {
            $e = $this->createActiveEnrollmentForSchool($school, $g['year_id']);
            DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $e->id)->update(['class_id' => $class, 'section_id' => $section]);
            DB::table(SchemaHelper::qualified('students', 'students'))->where('id', $e->student_id)->update(['full_name' => $name]);
            $students[$key] = (int) $e->id;
            foreach (['mid' => $g['session_id'], 'final' => $finalSession] as $exam => $session) {
                $seats[$key][$exam] = (int) DB::table($t('exam_enrollments'))->insertGetId([
                    'exam_session_id' => $session, 'school_id' => $school, 'enrollment_id' => $e->id, 'status' => 1, 'created_at' => now(),
                ]);
            }
        }

        return $g + compact('school', 'midType', 'finalType', 'finalExam', 'finalSession', 'class', 'section', 'students', 'seats');
    }

    private function as(string $role, int $school): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->{'grant'.$role}($user, $school);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        return $user;
    }

    private function post_(string $url, array $body, string $key): TestResponse
    {
        return $this->from('/exams')->post($url, $body, ['X-Idempotency-Key' => $key]);
    }

    /** Midterm and final grades of the demo through the entry sheet, then every grade finalized. */
    private function enterAndFinalizeGrades(array $d): void
    {
        $this->as('GradesManager', $d['school']);
        foreach (['mid' => [$d['session_id'], 0], 'final' => [$d['finalSession'], 1]] as $exam => [$session, $i]) {
            $this->markSessionInProgressForGradeEntry($session);
            $rows = [];
            foreach (self::SCORES as $key => $scores) {
                $rows[] = ['exam_enrollment_id' => $d['seats'][$key][$exam], 'score' => $scores[$i], 'is_absent' => $scores[$i] === null];
            }
            $this->post_("/grades/sheet/{$session}", ['rows' => $rows], "demo-sheet-{$exam}")->assertSessionHas('success', 'flash.grades.sheetSaved');
        }
        foreach ($this->currentGrades($d) as $grade) {
            $this->post_("/grades/{$grade->id}/finalize", ['academic_year_id' => $d['year_id']], "demo-final-{$grade->id}")
                ->assertSessionHas('success', 'flash.grades.finalized');
        }
    }

    /** @return list<object> */
    private function currentGrades(array $d): array
    {
        return DB::table(SchemaHelper::qualified('exams', 'student_grades'))
            ->whereIn('enrollment_id', $d['students'])->where('is_current', true)->orderBy('id')->get(['id', 'enrollment_id', 'status'])->all();
    }

    /** Runs a result sheet action as a results manager; returns enrollment → status. @return array<int, string> */
    private function results(array $d, string $path, array $body, string $key, string $outcomeKey): array
    {
        $this->as('ResultsManager', $d['school']);
        $response = $this->from('/results')->post($path, $body + ['academic_year_id' => $d['year_id'], 'class_id' => $d['class']], ['X-Idempotency-Key' => $key]);

        return array_column($response->getSession()->get($outcomeKey)['outcomes'] ?? [], 'status', 'enrollment_id');
    }

    private function termResults(array $d, string $mode): array
    {
        return $this->results($d, "/results/sheet/{$mode}", ['term_id' => $d['term_id'], 'subject_id' => $d['subject_id']], "demo-term-{$mode}", 'term_results_outcome');
    }

    private function examStatus(int $exam): int
    {
        return (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $exam)->value('status');
    }

    private function sessionRow(int $session): object
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->where('id', $session)->first();
    }

    /** A Draft exam of the demo year (created through the web), with one Scheduled session of the subject. @return array{0: int, 1: int} */
    private function draftExamWithSession(array $d, string $key): array
    {
        $this->as('GradesManager', $d['school']);
        $grade = $this->createGradeLevel('G-'.$key);
        $this->createCurriculumWithSubjects($d['school'], $d['year_id'], $grade, [$d['subject_id']]);
        $this->post_('/exams', [
            'academic_year_id' => $d['year_id'], 'term_id' => $d['term_id'], 'exam_type_id' => $d['midType'],
            'name' => 'اختبار تجريبي '.$key, 'start_date' => '2026-12-20', 'end_date' => '2026-12-24',
        ], "demo-create-{$key}")->assertSessionHas('success', 'flash.exams.created');
        $exam = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('name', 'اختبار تجريبي '.$key)->value('id');
        $this->post_("/exams/{$exam}/sessions", [
            'subject_id' => $d['subject_id'], 'session_date' => '2026-12-21', 'start_time' => '09:00:00', 'end_time' => '10:30:00',
            'max_grade' => 100, 'pass_grade' => 50,
        ], "demo-session-{$key}")->assertSessionHas('success', 'flash.exams.sessionCreated');

        return [$exam, (int) DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->where('exam_id', $exam)->value('id')];
    }

    #[Test]
    public function the_demo_term_results_follow_the_40_60_weights(): void
    {
        $d = $this->demo();
        $this->enterAndFinalizeGrades($d);
        $this->assertCount(6, $this->currentGrades($d), 'sara 2 + ali 2 + maryam 2 (her absence is a grade row too); hasan none');

        $this->assertEquals([ // the sheet lists students by name
            $d['students']['sara'] => 'calculated', $d['students']['ali'] => 'calculated',
            $d['students']['maryam'] => 'calculated', $d['students']['hasan'] => 'results.term_no_eligible_grades',
        ], $this->termResults($d, 'calculate'));
        $finalized = $this->termResults($d, 'finalize');
        $this->assertSame('finalized', $finalized[$d['students']['sara']]);

        $official = DB::table(SchemaHelper::qualified('results', 'term_results'))->whereIn('enrollment_id', $d['students'])
            ->where('is_current_official', true)->get(['enrollment_id', 'weighted_total', 'incomplete'])->keyBy('enrollment_id');
        $this->assertSame('86.00', (string) $official[$d['students']['sara']]->weighted_total, '0.4×80 + 0.6×90');
        $this->assertSame('48.00', (string) $official[$d['students']['ali']]->weighted_total, '0.4×45 + 0.6×50');
        $this->assertSame('42.00', (string) $official[$d['students']['maryam']]->weighted_total, 'absent midterm counts nothing');
        $this->assertTrue((bool) $official[$d['students']['maryam']]->incomplete, 'an absence marks the result incomplete');
        $this->assertFalse((bool) $official[$d['students']['sara']]->incomplete);
    }

    #[Test]
    public function annual_gpa_ranking_and_transcripts_complete_the_demo_year(): void
    {
        $d = $this->demo();
        $this->enterAndFinalizeGrades($d);
        $this->termResults($d, 'calculate');
        $this->termResults($d, 'finalize');
        foreach (['annual', 'gpa'] as $target) {
            $this->results($d, '/results/year-sheet/calculate', ['target' => $target], "demo-{$target}-c", 'year_results_outcome');
            $out = $this->results($d, '/results/year-sheet/finalize', ['target' => $target], "demo-{$target}-f", 'year_results_outcome');
            $this->assertSame('finalized', $out[$d['students']['sara']], "{$target} finalized");
        }
        $this->post('/results/year-sheet/ranking', ['academic_year_id' => $d['year_id'], 'class_id' => $d['class']], ['X-Idempotency-Key' => 'demo-rank'])
            ->assertSessionHas('success', 'flash.results.year.ranking');
        $issued = $this->results($d, '/results/year-sheet/transcripts', [], 'demo-tr', 'year_results_outcome');

        $ranks = DB::table(SchemaHelper::qualified('results', 'ranking_snapshot_entries'))->pluck('rank_position', 'enrollment_id');
        $this->assertSame(1, (int) $ranks[$d['students']['sara']]);
        $this->assertSame(2, (int) $ranks[$d['students']['ali']]);
        $this->assertSame(3, (int) $ranks[$d['students']['maryam']]);
        $this->assertArrayNotHasKey($d['students']['hasan'], $ranks->all(), 'no official GPA, not ranked');
        $this->assertSame('86.00', (string) DB::table(SchemaHelper::qualified('results', 'gpa_results'))
            ->where('enrollment_id', $d['students']['sara'])->where('is_current_official', true)->value('gpa_value'));
        $this->assertSame(['issued', 'issued', 'issued', 'results.transcript_official_gpa_missing'], array_values(array_map(
            static fn (int $e): string => $issued[$e], [$d['students']['sara'], $d['students']['ali'], $d['students']['maryam'], $d['students']['hasan']])));
    }

    #[Test]
    public function the_entry_sheet_refuses_a_score_above_the_maximum_without_losing_the_others(): void
    {
        $d = $this->demo();
        $this->as('GradesManager', $d['school']);
        $this->markSessionInProgressForGradeEntry($d['session_id']);
        $response = $this->post_("/grades/sheet/{$d['session_id']}", ['rows' => [
            ['exam_enrollment_id' => $d['seats']['sara']['mid'], 'score' => '150', 'is_absent' => false],
            ['exam_enrollment_id' => $d['seats']['ali']['mid'], 'score' => '75', 'is_absent' => false],
        ]], 'demo-over-max');

        $response->assertSessionHas('error', 'flash.grades.sheetPartial');
        $outcomes = array_column($response->getSession()->get('grade_sheet_outcomes'), 'status', 'exam_enrollment_id');
        $this->assertNotSame('saved', $outcomes[$d['seats']['sara']['mid']]);
        $this->assertSame('saved', $outcomes[$d['seats']['ali']['mid']]);
        $this->assertSame(['ali'], array_keys(array_filter($d['students'], fn (int $e): bool => DB::table(SchemaHelper::qualified('exams', 'student_grades'))
            ->where('enrollment_id', $e)->where('is_current', true)->exists())));
    }

    #[Test]
    public function a_draft_exam_is_edited_but_its_type_locks_once_scheduled(): void
    {
        $d = $this->demo();
        [$exam] = $this->draftExamWithSession($d, 'EDIT');
        $this->assertSame(ExamStatus::Draft->value, $this->examStatus($exam));

        $this->post_("/exams/{$exam}/update", ['name' => 'اختبار شهري', 'exam_type_id' => $d['finalType'], 'end_date' => '2026-12-28'], 'demo-edit-1')
            ->assertSessionHas('success', 'flash.exams.updated');
        $row = DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $exam)->first();
        $this->assertSame(['اختبار شهري', $d['finalType'], '2026-12-28'], [$row->name, (int) $row->exam_type_id, (string) $row->end_date]);

        $this->post_("/exams/{$exam}/update", ['target_status' => ExamStatus::Scheduled->value], 'demo-edit-2')->assertSessionHas('success');
        $this->post_("/exams/{$exam}/update", ['exam_type_id' => $d['midType']], 'demo-edit-3')
            ->assertSessionHas('error', 'exams.update.field_not_mutable');
        $this->post_("/exams/{$exam}/update", ['name' => 'اسم بعد الجدولة'], 'demo-edit-4')->assertSessionHas('success');
        $row = DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $exam)->first();
        $this->assertSame([$d['finalType'], 'اسم بعد الجدولة', ExamStatus::Scheduled->value], [(int) $row->exam_type_id, $row->name, (int) $row->status]);
    }

    #[Test]
    public function the_exam_status_only_moves_forward_and_completion_waits_for_its_sessions(): void
    {
        $d = $this->demo();
        [$exam] = $this->draftExamWithSession($d, 'FLOW');

        $this->post_("/exams/{$exam}/update", ['target_status' => ExamStatus::InProgress->value], 'demo-flow-skip')
            ->assertSessionHas('error', 'exams.update.invalid_transition');
        $this->post_("/exams/{$exam}/update", ['target_status' => ExamStatus::Scheduled->value], 'demo-flow-1')->assertSessionHas('success');
        $this->post_("/exams/{$exam}/update", ['target_status' => ExamStatus::InProgress->value], 'demo-flow-2')->assertSessionHas('success');
        $this->post_("/exams/{$exam}/update", ['target_status' => ExamStatus::Completed->value], 'demo-flow-3')
            ->assertSessionHas('error', 'exams.completion.has_scheduled_sessions');
        $this->post_("/exams/{$exam}/update", ['target_status' => ExamStatus::Cancelled->value], 'demo-flow-4')->assertSessionHasErrors('target_status');

        $this->assertSame(ExamStatus::InProgress->value, $this->examStatus($exam));
    }

    #[Test]
    public function a_scheduled_session_is_edited_and_locks_once_started(): void
    {
        $d = $this->demo();
        [$exam, $session] = $this->draftExamWithSession($d, 'SESS');

        $this->post_("/exams/{$exam}/sessions/{$session}/update", ['session_date' => '2026-12-22', 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'max_grade' => 50, 'pass_grade' => 25], 'demo-sess-1')
            ->assertSessionHas('success', 'flash.exams.sessionUpdated');
        $row = $this->sessionRow($session);
        $this->assertSame(['2026-12-22', '10:00:00', '12:00:00', 50, 25], [(string) $row->session_date, (string) $row->start_time, (string) $row->end_time, (int) $row->max_grade, (int) $row->pass_grade]);

        $this->post_("/exams/{$exam}/sessions/{$session}/update", ['pass_grade' => 60], 'demo-sess-2')
            ->assertSessionHas('error', 'exams.validation.invalid_pass_max_grade');
        $this->post_("/exams/{$exam}/sessions/{$session}/update", ['start_time' => '13:00:00'], 'demo-sess-3')
            ->assertSessionHas('error', 'exams.validation.invalid_session_time_range');
        $this->post_("/exams/{$exam}/update", ['target_status' => ExamStatus::Scheduled->value], 'demo-sess-sched');
        $this->post_("/exams/{$exam}/sessions/{$session}/open", [], 'demo-sess-open')->assertSessionHas('success');
        $this->post_("/exams/{$exam}/sessions/{$session}/update", ['session_date' => '2026-12-23'], 'demo-sess-4')
            ->assertSessionHas('error', 'exams.update.session_metadata_not_allowed');
        $this->assertSame('2026-12-22', (string) $this->sessionRow($session)->session_date);
    }

    #[Test]
    public function a_session_with_grades_is_never_cancelled_and_an_empty_one_is(): void
    {
        $d = $this->demo();
        $this->enterAndFinalizeGrades($d);
        $this->post_("/exams/{$d['exam_id']}/sessions/{$d['session_id']}/cancel", [], 'demo-cs-1')
            ->assertSessionHas('error', 'exams.cancel.session_current_grade_exists');
        $this->assertSame(ExamSessionStatus::InProgress->value, (int) $this->sessionRow($d['session_id'])->status);

        [$exam, $session] = $this->draftExamWithSession($d, 'CSES');
        $this->post_("/exams/{$exam}/sessions/{$session}/cancel", [], 'demo-cs-2')->assertSessionHas('success', 'flash.exams.sessionCancelled');
        $this->post_("/exams/{$exam}/sessions/{$session}/cancel", [], 'demo-cs-2')->assertSessionHas('success');
        $this->assertSame(ExamSessionStatus::Cancelled->value, (int) $this->sessionRow($session)->status);
        $this->post_("/exams/{$exam}/sessions/{$session}/update", ['max_grade' => 80], 'demo-cs-3')
            ->assertSessionHas('error', 'exams.update.session_metadata_not_allowed');
    }

    #[Test]
    public function cancelling_an_exam_cascades_to_its_sessions_but_never_past_grades(): void
    {
        $d = $this->demo();
        $this->enterAndFinalizeGrades($d);
        $this->post_("/exams/{$d['finalExam']}/cancel", [], 'demo-ce-1')->assertSessionHas('error', 'exams.cancel.current_grade_exists');
        $this->assertSame(ExamStatus::Scheduled->value, $this->examStatus($d['finalExam']));

        [$exam, $session] = $this->draftExamWithSession($d, 'CEXM');
        $this->post_("/exams/{$exam}/cancel", [], 'demo-ce-2')->assertSessionHas('success', 'flash.exams.cancelled');
        $this->assertSame(ExamStatus::Cancelled->value, $this->examStatus($exam));
        $this->assertSame(ExamSessionStatus::Cancelled->value, (int) $this->sessionRow($session)->status);
        $this->post_("/exams/{$exam}/update", ['name' => 'بعد الإلغاء'], 'demo-ce-3')->assertSessionHas('error', 'exams.update.metadata_not_allowed');
        $this->post_("/exams/{$exam}/cancel", [], 'demo-ce-4')->assertSessionHas('error', 'exams.cancel.exam_already_cancelled');
    }

    #[Test]
    public function another_schools_exam_and_a_session_of_another_exam_are_out_of_reach(): void
    {
        $d = $this->demo();
        $other = $this->createSchool('SCH-DEMO-2', 'مدرسة أخرى');
        $o = $this->seedExamGradeGraph($other, suffix: 'DEMO2');
        $this->as('GradesManager', $d['school']);

        $this->post_("/exams/{$o['exam_id']}/update", ['name' => 'اختراق'], 'demo-x-1')->assertSessionHas('error');
        $this->post_("/exams/{$o['exam_id']}/cancel", [], 'demo-x-2')->assertSessionHas('error');
        $this->post_("/exams/{$d['exam_id']}/sessions/{$o['session_id']}/update", ['max_grade' => 10], 'demo-x-3')->assertNotFound();
        $this->post_("/exams/{$d['exam_id']}/sessions/{$o['session_id']}/cancel", [], 'demo-x-4')->assertNotFound();
        $this->post_("/exams/{$d['exam_id']}/sessions/{$d['finalSession']}/cancel", [], 'demo-x-5')->assertNotFound();

        $this->assertSame('Exam DEMO2', DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $o['exam_id'])->value('name'));
        $this->assertSame(ExamStatus::Scheduled->value, $this->examStatus($o['exam_id']));
        $this->assertSame(100, (int) $this->sessionRow($o['session_id'])->max_grade);
        $this->assertSame(ExamSessionStatus::Scheduled->value, (int) $this->sessionRow($d['finalSession'])->status);
    }

    #[Test]
    public function viewers_and_teachers_can_neither_edit_nor_cancel(): void
    {
        $d = $this->demo();
        foreach (['GradesViewer', 'GradesTeacher', 'ResultsManager'] as $role) {
            $this->as($role, $d['school']);
            $this->post_("/exams/{$d['exam_id']}/update", ['name' => 'x'], "demo-r-{$role}-1")->assertForbidden();
            $this->post_("/exams/{$d['exam_id']}/cancel", [], "demo-r-{$role}-2")->assertForbidden();
            $this->post_("/exams/{$d['exam_id']}/sessions/{$d['session_id']}/update", ['max_grade' => 10], "demo-r-{$role}-3")->assertForbidden();
            $this->post_("/exams/{$d['exam_id']}/sessions/{$d['session_id']}/cancel", [], "demo-r-{$role}-4")->assertForbidden();
        }
        $this->assertSame('امتحان نصف الفصل', DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $d['exam_id'])->value('name'));
        $this->assertSame(ExamSessionStatus::Scheduled->value, (int) $this->sessionRow($d['session_id'])->status);
    }
}
