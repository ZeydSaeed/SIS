<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
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
 * EXAMIS U9 — «كشف المرشحين»: the seats of one exam session from the web (confirm, present, absent, seat number,
 * withdraw, reopen) through the same commands, requests and permissions as the API.
 */
final class ExamRosterWebPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsApplicationExamGradeGraph;

    /** @return array<string, mixed> */
    private function scene(string $suffix = 'ROS'): array
    {
        $school = $this->createSchool('SCH-'.$suffix, 'Roster');
        $g = $this->seedExamGradeGraph($school, suffix: $suffix);

        return $g + ['school' => $school, 'base' => "/exams/{$g['exam_id']}/sessions/{$g['session_id']}"];
    }

    private function as(string $role, int $school): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->{'grant'.$role}($user, $school);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        return $user;
    }

    private function seat(int $seat): object
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->where('id', $seat)->first(['status', 'seat_number']);
    }

    /** @param  array<string, mixed>  $data */
    private function act(array $s, string $path, array $data, string $key): TestResponse
    {
        return $this->from($s['base'])->post("{$s['base']}/seats/{$s['exam_enrollment_id']}{$path}", $data, ['X-Idempotency-Key' => $key]);
    }

    #[Test]
    public function the_roster_lists_every_seat_with_the_student_and_what_the_user_may_do(): void
    {
        $s = $this->scene();
        $name = (string) DB::table(SchemaHelper::qualified('students', 'students'))->where('id', $s['student_id'])->value('full_name');

        $this->as('GradesManager', $s['school']);
        $this->get($s['base'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('exams/session')
            ->where('session.id', $s['session_id'])
            ->where('session.exam_id', $s['exam_id'])
            ->has('seats.data', 1)
            ->where('seats.data.0.exam_enrollment_id', $s['exam_enrollment_id'])
            ->where('seats.data.0.student_name', $name)
            ->where('seats.data.0.seat_number', 'A1')
            ->where('seats.data.0.status', ExamEnrollmentStatus::Registered->value)
            ->where('seats.data.0.has_current_grade', false)
            ->where('can', ['update' => true, 'present' => true, 'cancel' => true, 'reopen' => true]));

        $this->as('GradesTeacher', $s['school']);
        $this->get($s['base'])->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('can', ['update' => false, 'present' => false, 'cancel' => false, 'reopen' => false]));
    }

    #[Test]
    public function the_manager_confirms_then_marks_present_once_the_session_is_open(): void
    {
        $s = $this->scene();
        $user = $this->as('GradesManager', $s['school']);

        $this->act($s, '', ['status' => 2], 'ros-confirm')->assertRedirect($s['base'])->assertSessionHas('success', 'flash.exams.seatConfirmed');
        $this->assertSame(ExamEnrollmentStatus::Confirmed->value, (int) $this->seat($s['exam_enrollment_id'])->status);

        // Scheduled session: presence is refused with one localized key, and nothing changes.
        $this->act($s, '/present', [], 'ros-present-early')->assertRedirect($s['base'])->assertSessionHas('error', 'exams.roster.action_refused');
        $this->assertSame(ExamEnrollmentStatus::Confirmed->value, (int) $this->seat($s['exam_enrollment_id'])->status);

        $this->markSessionInProgressForGradeEntry($s['session_id']);
        $this->act($s, '/present', [], 'ros-present')->assertSessionHas('success', 'flash.exams.seatPresent');
        $this->assertSame(ExamEnrollmentStatus::Present->value, (int) $this->seat($s['exam_enrollment_id'])->status);
        $this->assertTrue(SecurityAuditLogRecord::query()->where('action', 'exams.web.roster.present')->where('actor_id', $user->id)->exists());

        // A present seat's number is frozen.
        $this->act($s, '', ['seat_number' => 'B9'], 'ros-seat-frozen')->assertSessionHas('error', 'exams.roster.action_refused');
        $this->assertSame('A1', $this->seat($s['exam_enrollment_id'])->seat_number);
    }

    #[Test]
    public function the_manager_sets_the_seat_number_marks_absent_withdraws_and_reopens(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);

        $this->act($s, '', ['seat_number' => ' B9 '], 'ros-seat')->assertSessionHas('success', 'flash.exams.seatNumberSaved');
        $this->assertSame('B9', $this->seat($s['exam_enrollment_id'])->seat_number);

        $this->act($s, '/cancel', [], 'ros-withdraw')->assertSessionHas('success', 'flash.exams.seatWithdrawn');
        $this->assertSame(ExamEnrollmentStatus::Withdrawn->value, (int) $this->seat($s['exam_enrollment_id'])->status);

        $this->act($s, '/reopen', [], 'ros-reopen')->assertSessionHas('success', 'flash.exams.seatReopened');
        $this->assertSame(ExamEnrollmentStatus::Registered->value, (int) $this->seat($s['exam_enrollment_id'])->status);

        $this->act($s, '', ['status' => 4], 'ros-absent')->assertSessionHas('success', 'flash.exams.seatAbsent');
        $this->assertSame(ExamEnrollmentStatus::Absent->value, (int) $this->seat($s['exam_enrollment_id'])->status);

        // Absent is final: neither withdraw nor reopen moves it.
        $this->act($s, '/cancel', [], 'ros-withdraw-absent')->assertSessionHas('error', 'exams.roster.action_refused');
        $this->act($s, '/reopen', [], 'ros-reopen-absent')->assertSessionHas('error', 'exams.roster.action_refused');
        $this->assertSame(ExamEnrollmentStatus::Absent->value, (int) $this->seat($s['exam_enrollment_id'])->status);
    }

    #[Test]
    public function invalid_or_keyless_requests_change_nothing(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);

        $this->act($s, '', ['status' => 5], 'ros-bad-status')->assertSessionHasErrors('status');
        $this->act($s, '', ['status' => 2, 'school_id' => 99], 'ros-school')->assertSessionHasErrors('school_id');
        $this->from($s['base'])->post("{$s['base']}/seats/{$s['exam_enrollment_id']}", ['status' => 2])->assertSessionHasErrors('X-Idempotency-Key');
        $this->assertSame(ExamEnrollmentStatus::Registered->value, (int) $this->seat($s['exam_enrollment_id'])->status);
    }

    #[Test]
    public function a_seat_outside_the_url_session_exam_or_school_is_not_found(): void
    {
        $s = $this->scene();
        $this->as('GradesManager', $s['school']);
        $other = $this->seedExamGradeGraph($s['school'], $s['year_id'], 'ROS2');
        $foreignSchool = $this->createSchool('SCH-ROSF', 'Foreign');
        $foreign = $this->seedExamGradeGraph($foreignSchool, $s['year_id'], 'ROSF');

        // Another session's seat under this session; this session under another exam; another school's seat and page.
        $this->post("{$s['base']}/seats/{$other['exam_enrollment_id']}", ['status' => 2], ['X-Idempotency-Key' => 'ros-x1'])->assertNotFound();
        $this->post("/exams/{$other['exam_id']}/sessions/{$s['session_id']}/seats/{$s['exam_enrollment_id']}", ['status' => 2], ['X-Idempotency-Key' => 'ros-x2'])->assertNotFound();
        $this->post("/exams/{$foreign['exam_id']}/sessions/{$foreign['session_id']}/seats/{$foreign['exam_enrollment_id']}/cancel", [], ['X-Idempotency-Key' => 'ros-x3'])->assertNotFound();
        $this->get("/exams/{$other['exam_id']}/sessions/{$s['session_id']}")->assertNotFound();
        $this->get("/exams/{$foreign['exam_id']}/sessions/{$foreign['session_id']}")->assertNotFound();

        foreach ([$s['exam_enrollment_id'], $other['exam_enrollment_id'], $foreign['exam_enrollment_id']] as $seat) {
            $this->assertSame(ExamEnrollmentStatus::Registered->value, (int) $this->seat($seat)->status);
        }
    }

    #[Test]
    public function users_without_seat_rights_are_refused(): void
    {
        $s = $this->scene();
        foreach (['GradesTeacher', 'GradesViewer'] as $role) {
            $this->as($role, $s['school']);
            $this->act($s, '', ['status' => 2], "ros-{$role}-confirm")->assertForbidden();
            $this->act($s, '/present', [], "ros-{$role}-present")->assertForbidden();
            $this->act($s, '/cancel', [], "ros-{$role}-cancel")->assertForbidden();
            $this->act($s, '/reopen', [], "ros-{$role}-reopen")->assertForbidden();
            $this->assertSame(ExamEnrollmentStatus::Registered->value, (int) $this->seat($s['exam_enrollment_id'])->status, $role);
        }
    }
}
