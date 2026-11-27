<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use App\Domain\Exams\ValueObjects\ExamStatus;
use App\Models\User;
use App\Security\Context\SchoolContext;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * EXAMIS 7.2 — editing and cancelling exams and sessions from the web (complements ExamAdministrationWebPostgreSqlTest,
 * which covers create / add session / open / close): the handlers' rules surface as flash errors, a repeated key
 * replays, another school's exam is not found, a non-numeric id is a 404 (L-F1), and the pages carry names, not ids.
 */
final class ExamManagementWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @return array<string, mixed> */
    private function scene(string $suffix = 'EXMGT'): array
    {
        $school = $this->createSchool('SCH-'.$suffix, 'Exam management '.$suffix);
        $g = $this->seedExamGradeGraph($school, suffix: $suffix);

        return $g + ['school' => $school];
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

    private function exam(int $id): object
    {
        return DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $id)->first();
    }

    private function sessionRow(int $id): object
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_sessions'))->where('id', $id)->first();
    }

    #[Test]
    public function the_manager_edits_an_exam_and_a_repeated_key_replays(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $url = "/exams/{$s['exam_id']}/update";

        $this->post_($url, ['name' => 'امتحان معدّل', 'end_date' => '2026-11-20'], 'mgt-upd-1')
            ->assertRedirect("/exams/{$s['exam_id']}")->assertSessionHas('success', 'flash.exams.updated');
        $this->post_($url, ['name' => 'امتحان معدّل', 'end_date' => '2026-11-20'], 'mgt-upd-1')->assertSessionHas('success');
        $this->assertSame(['امتحان معدّل', '2026-11-20'], [$this->exam($s['exam_id'])->name, substr((string) $this->exam($s['exam_id'])->end_date, 0, 10)]);

        $this->post_($url, ['end_date' => '2026-10-01'], 'mgt-upd-dates')->assertSessionHas('error');
        $this->post_($url, ['school_id' => 99], 'mgt-upd-school')->assertSessionHasErrors('school_id');
        $this->post_($url, ['academic_year_id' => 99], 'mgt-upd-year')->assertSessionHasErrors('academic_year_id');
        $this->assertSame('2026-11-20', substr((string) $this->exam($s['exam_id'])->end_date, 0, 10));
    }

    #[Test]
    public function the_exam_moves_only_along_its_lifecycle(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $url = "/exams/{$s['exam_id']}/update";

        $this->post_($url, ['target_status' => ExamStatus::Completed->value], 'mgt-st-skip')->assertSessionHas('error');
        $this->assertSame(ExamStatus::Scheduled->value, (int) $this->exam($s['exam_id'])->status);
        $this->post_($url, ['target_status' => ExamStatus::InProgress->value], 'mgt-st-go')->assertSessionHas('success', 'flash.exams.updated');
        $this->assertSame(ExamStatus::InProgress->value, (int) $this->exam($s['exam_id'])->status);
        $this->post_($url, ['target_status' => ExamStatus::Cancelled->value], 'mgt-st-cancel')->assertSessionHasErrors('target_status');
    }

    #[Test]
    public function the_manager_edits_and_cancels_a_session_and_an_exam(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $base = "/exams/{$s['exam_id']}/sessions/{$s['session_id']}";

        $this->post_("{$base}/update", ['session_date' => '2026-11-06', 'start_time' => '10:00:00', 'end_time' => '12:00:00', 'max_grade' => 60, 'pass_grade' => 30], 'mgt-ses-upd')
            ->assertSessionHas('success', 'flash.exams.sessionUpdated');
        $session = $this->sessionRow($s['session_id']);
        $this->assertSame(['2026-11-06', 60, 30], [substr((string) $session->session_date, 0, 10), (int) $session->max_grade, (int) $session->pass_grade]);
        $this->post_("{$base}/update", ['subject_id' => $s['subject_id']], 'mgt-ses-subj')->assertSessionHasErrors('subject_id');

        $this->post_("{$base}/cancel", [], 'mgt-ses-cancel')->assertSessionHas('success', 'flash.exams.sessionCancelled');
        $this->assertSame(ExamSessionStatus::Cancelled->value, (int) $this->sessionRow($s['session_id'])->status);

        $this->post_("/exams/{$s['exam_id']}/cancel", [], 'mgt-exam-cancel')->assertSessionHas('success', 'flash.exams.cancelled');
        $this->assertSame(ExamStatus::Cancelled->value, (int) $this->exam($s['exam_id'])->status);
        $this->post_("/exams/{$s['exam_id']}/update", ['name' => 'بعد الإلغاء'], 'mgt-after-cancel')->assertSessionHas('error');
    }

    #[Test]
    public function a_session_with_a_current_grade_cannot_be_cancelled_nor_its_exam(): void
    {
        $s = $this->scene();
        $this->markSessionInProgressForGradeEntry($s['session_id']);
        $this->enterGradeForSeat($s);
        $this->as('GradesManager', $s['school']);

        $this->post_("/exams/{$s['exam_id']}/sessions/{$s['session_id']}/cancel", [], 'mgt-graded-ses')->assertSessionHas('error');
        $this->post_("/exams/{$s['exam_id']}/cancel", [], 'mgt-graded-exam')->assertSessionHas('error');
        $this->assertNotSame(ExamSessionStatus::Cancelled->value, (int) $this->sessionRow($s['session_id'])->status);
        $this->assertNotSame(ExamStatus::Cancelled->value, (int) $this->exam($s['exam_id'])->status);
    }

    #[Test]
    public function only_exam_administrators_edit_or_cancel_and_another_school_is_not_found(): void
    {
        $s = $this->scene();
        $foreign = $this->scene('EXMGT-F');
        foreach (['GradesTeacher', 'GradesViewer'] as $role) {
            $this->as($role, $s['school']);
            $this->post_("/exams/{$s['exam_id']}/update", ['name' => 'x'], "mgt-{$role}-upd")->assertForbidden();
            $this->post_("/exams/{$s['exam_id']}/cancel", [], "mgt-{$role}-cancel")->assertForbidden();
            $this->post_("/exams/{$s['exam_id']}/sessions/{$s['session_id']}/cancel", [], "mgt-{$role}-scancel")->assertForbidden();
        }
        $this->as('GradesManager', $s['school']);
        $this->post_("/exams/{$foreign['exam_id']}/update", ['name' => 'x'], 'mgt-foreign-upd')->assertNotFound();
        $this->post_("/exams/{$foreign['exam_id']}/sessions/{$foreign['session_id']}/cancel", [], 'mgt-foreign-scancel')->assertNotFound();
        $this->post_("/exams/{$s['exam_id']}/sessions/{$foreign['session_id']}/update", ['max_grade' => 10], 'mgt-cross-ses')->assertNotFound();
        $this->assertSame(['Exam EXMGT', 'Exam EXMGT-F'], [$this->exam($s['exam_id'])->name, $this->exam($foreign['exam_id'])->name]);
        $this->assertSame(ExamSessionStatus::Scheduled->value, (int) $this->sessionRow($foreign['session_id'])->status);
    }

    #[Test]
    public function a_non_numeric_exam_id_is_not_found_and_the_page_names_the_type(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);

        $this->get('/exams/abc')->assertNotFound();
        $this->get("/exams/{$s['exam_id']}")->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('admin.can_update', true)->where('admin.can_cancel', true)->where('admin.can_update_session', true)
            ->where('admin.exam_types', fn ($types) => collect($types)->pluck('id')->contains((int) $this->exam($s['exam_id'])->exam_type_id)));
    }

    /** A current grade on the scene's seat, through the real handler. */
    private function enterGradeForSeat(array $s): void
    {
        $manager = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantGradesManager($manager, $s['school']);
        app(SchoolContext::class)->set($s['school']);
        app(EnterStudentGradeHandler::class)->handle(new EnterStudentGradeCommand(
            schoolId: $s['school'], examEnrollmentId: $s['exam_enrollment_id'], score: '70', isAbsent: false,
            enteredBy: (int) $manager->id, idempotencyKey: 'mgt-grade-'.$s['exam_enrollment_id'],
        ));
        app(SchoolContext::class)->clear();
    }
}
