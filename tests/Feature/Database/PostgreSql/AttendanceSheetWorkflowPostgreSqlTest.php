<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Infrastructure\Jobs\ProcessOutboxJob;
use App\Infrastructure\Persistence\Outbox\EloquentOutboxRepository;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Attendance\SeedsAttendanceLessonGraph;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class AttendanceSheetWorkflowPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsAttendanceLessonGraph;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::DURING_LESSON_UTC);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function teacher_day_shows_the_real_timetable_lesson_and_opening_is_idempotent(): void
    {
        $schoolId = $this->createSchool('SCH-AW1', 'Attendance W1');
        $g = $this->seedLessonGraph($schoolId);
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);

        $this->get('/attendance/my?date='.self::LESSON_DATE)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/my-day')
                ->where('linked', true)
                ->has('day.lessons', 1)
                ->where('day.lessons.0.schedule_id', $g['schedule_id'])
                ->where('day.lessons.0.window', 'open')
                ->where('day.lessons.0.session', null));

        $open = ['schedule_id' => $g['schedule_id'], 'date' => self::LESSON_DATE];
        $first = $this->post('/attendance/lessons/open', $open, ['X-Idempotency-Key' => 'open-1']);
        $sessionId = (int) DB::table('attendance.sessions')->where('schedule_id', $g['schedule_id'])->value('id');
        $first->assertRedirect("/attendance/sheets/{$sessionId}");
        $this->post('/attendance/lessons/open', $open, ['X-Idempotency-Key' => 'open-2'])->assertRedirect("/attendance/sheets/{$sessionId}");

        $this->assertSame(1, DB::table('attendance.sessions')->where('schedule_id', $g['schedule_id'])->count());
        $session = $this->sheet($sessionId);
        $this->assertSame(1, (int) $session->sheet_status);
        $this->assertSame(2, (int) $session->origin);
        $this->assertSame($g['teacher_id'], (int) $session->teacher_id);
        $this->assertSame('08:00', json_decode((string) $session->lesson_snapshot, true)['start_time']);

        $this->get("/attendance/sheets/{$sessionId}")
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('attendance/sheet')
                ->has('sheet.rows', 2)
                ->where('sheet.counts.expected', 2)
                ->where('sheet.counts.unmarked', 2)
                ->where('sheet.permissions.can_submit', true)
                ->where('sheet.permissions.review_actions', []));
    }

    #[Test]
    public function full_lifecycle_draft_submit_return_resubmit_approve(): void
    {
        $schoolId = $this->createSchool('SCH-AW2', 'Attendance W2');
        $g = $this->seedLessonGraph($schoolId);
        $manager = User::factory()->create();
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $sessionId = $this->openLesson($g);
        [$e1, $e2] = $g['enrollments'];

        // Draft: one present, one still unmarked.
        $this->post("/attendance/sheets/{$sessionId}/draft", $this->marksPayload(1, [$e1, $e2], [1, 0]), ['X-Idempotency-Key' => 'd1'])
            ->assertRedirect()->assertSessionHas('attendanceResult.action', 'draft')->assertSessionHas('attendanceResult.version', 2);
        $this->assertSame(2, (int) $this->sheet($sessionId)->version);
        // Retried draft (same key) replays — no second version bump.
        $this->post("/attendance/sheets/{$sessionId}/draft", $this->marksPayload(1, [$e1, $e2], [1, 0]), ['X-Idempotency-Key' => 'd1'])->assertRedirect();
        $this->assertSame(2, (int) $this->sheet($sessionId)->version);
        // A stale tab (old version, new key) is rejected, not applied.
        $this->post("/attendance/sheets/{$sessionId}/draft", $this->marksPayload(1, [$e1, $e2], [2, 2]), ['X-Idempotency-Key' => 'd-stale'])
            ->assertSessionHas('error', 'attendance.sheet_version_conflict');
        $this->assertSame(1, (int) DB::table('attendance.records')->where('session_id', $sessionId)->where('enrollment_id', $e1)->value('status'));

        // Submit needs the explicit confirmation; students left unmarked are recorded present (only exceptions are marked).
        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(2, [$e1, $e2], [1, 2]), ['X-Idempotency-Key' => 's0'])
            ->assertSessionHasErrors('confirm');
        $this->assertSame(1, (int) $this->sheet($sessionId)->sheet_status);

        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(2, [$e1, $e2], [2, 0], true), ['X-Idempotency-Key' => 's1'])
            ->assertSessionHas('success', 'flash.attendance.sheetSubmitted')
            ->assertSessionHas('attendanceResult.sheet_status', 2);
        $this->assertSame(1, (int) DB::table('attendance.records')->where('session_id', $sessionId)->where('enrollment_id', $e2)->value('status'));
        $this->assertSame(2, (int) $this->sheet($sessionId)->sheet_status);
        // Double click: same key replays; a second key fails the version guard — still one submission.
        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(2, [$e1, $e2], [1, 2], true), ['X-Idempotency-Key' => 's1'])
            ->assertSessionHas('success', 'flash.attendance.sheetSubmitted');
        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(2, [$e1, $e2], [1, 2], true), ['X-Idempotency-Key' => 's2'])
            ->assertSessionHas('error', 'attendance.sheet_version_conflict');
        $this->assertSame(1, (int) $this->sheet($sessionId)->submission_count);
        // Teacher can no longer change marks; the teacher cannot review.
        $this->post("/attendance/sheets/{$sessionId}/draft", $this->marksPayload(3, [$e1, $e2], [2, 2]), ['X-Idempotency-Key' => 'd-after'])
            ->assertSessionHas('error', 'attendance.sheet_not_editable');
        $this->post("/attendance/sheets/{$sessionId}/review", ['action' => 'approve', 'version' => 3])->assertForbidden();

        // Manager: return requires a reason.
        $this->actAsAttendanceManager($manager, $schoolId);
        $this->post("/attendance/sheets/{$sessionId}/review", ['action' => 'return', 'version' => 3], ['X-Idempotency-Key' => 'r0'])
            ->assertSessionHasErrors('reason');
        $this->post("/attendance/sheets/{$sessionId}/review", ['action' => 'return', 'version' => 3, 'reason' => 'الطالب الثاني كان حاضراً'], ['X-Idempotency-Key' => 'r1'])
            ->assertSessionHas('success', 'flash.attendance.review.return');
        $returned = $this->sheet($sessionId);
        $this->assertSame(5, (int) $returned->sheet_status);
        $this->assertSame('الطالب الثاني كان حاضراً', $returned->review_note);

        // Teacher corrects and resubmits.
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $this->get('/attendance/my?date='.self::LESSON_DATE)
            ->assertInertia(fn ($page) => $page->where('day.notices.0.sheet_status', 5)->where('day.notices.0.review_note', 'الطالب الثاني كان حاضراً'));
        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(4, [$e1, $e2], [1, 1], true), ['X-Idempotency-Key' => 's3'])
            ->assertSessionHas('success', 'flash.attendance.sheetSubmitted');
        $this->assertSame(2, (int) $this->sheet($sessionId)->submission_count);

        // Manager approves; a second approval is refused; the session is closed for marking.
        $this->actAsAttendanceManager($manager, $schoolId);
        $this->post("/attendance/sheets/{$sessionId}/review", ['action' => 'approve', 'version' => 5], ['X-Idempotency-Key' => 'a1'])
            ->assertSessionHas('success', 'flash.attendance.review.approve');
        $this->post("/attendance/sheets/{$sessionId}/review", ['action' => 'approve', 'version' => 6], ['X-Idempotency-Key' => 'a2'])
            ->assertSessionHas('error', 'attendance.sheet_transition_invalid');
        $approved = $this->sheet($sessionId);
        $this->assertSame(4, (int) $approved->sheet_status);
        $this->assertSame(2, (int) $approved->status);
        $this->assertSame($manager->id, (int) $approved->reviewed_by);

        // History: opened, draft, submitted, returned, resubmitted, approved (+ draft saves).
        $events = DB::table('attendance.sheet_events')->where('session_id', $sessionId)->orderBy('id')->pluck('event_type')->map(fn ($v) => (int) $v)->all();
        $this->assertSame([1, 2, 3, 6, 9, 5], $events);

        // Outbox → business audit + in-app notifications (teacher told about return / approval).
        (new ProcessOutboxJob)->handle(app(EloquentOutboxRepository::class));
        $this->assertGreaterThanOrEqual(4, DB::table('audit.audit_logs')->where('entity_type', 'attendance_session')->where('entity_id', $sessionId)->count());
        $this->assertTrue(DB::table('communication.messages')->where('recipient_type', 'user')->where('recipient_id', $g['teacher_user']->id)->where('channel', 3)->exists());
        $this->assertTrue(DB::table('communication.messages')->where('recipient_id', $manager->id)->where('subject', 'كشف حضور بانتظار المراجعة')->exists());
    }

    #[Test]
    public function legacy_mark_path_cannot_change_a_submitted_sheet(): void
    {
        $schoolId = $this->createSchool('SCH-AW3', 'Attendance W3');
        $g = $this->seedLessonGraph($schoolId, 1);
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $sessionId = $this->openLesson($g);
        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(1, $g['enrollments'], [1], true), ['X-Idempotency-Key' => 'l1'])
            ->assertSessionHas('success', 'flash.attendance.sheetSubmitted');

        $this->postJson("/api/v1/attendance/sessions/{$sessionId}/marks", [
            'academic_year_id' => $g['year_id'],
            'records' => [['student_id' => $g['students'][0], 'enrollment_id' => $g['enrollments'][0], 'status' => 2]],
        ], ['X-Idempotency-Key' => 'legacy-1'])->assertStatus(422)->assertJsonPath('error_code', 'attendance.sheet_not_editable');
        $this->assertSame(1, (int) DB::table('attendance.records')->where('session_id', $sessionId)->value('status'));
    }

    #[Test]
    public function teachers_cannot_open_or_read_lessons_of_other_teachers(): void
    {
        $schoolId = $this->createSchool('SCH-AW4', 'Attendance W4');
        $g = $this->seedLessonGraph($schoolId);
        $other = $this->seedTeacher($schoolId, $g['year_id']);

        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $sessionId = $this->openLesson($g);

        $this->actAsAttendanceTeacher($other['user'], $schoolId);
        $this->post('/attendance/lessons/open', ['schedule_id' => $g['schedule_id'], 'date' => self::LESSON_DATE], ['X-Idempotency-Key' => 'x1'])
            ->assertSessionHas('error', 'attendance.lesson_access_denied');
        $this->get("/attendance/sheets/{$sessionId}")->assertSessionHas('error', 'attendance.lesson_access_denied');
        $this->post("/attendance/sheets/{$sessionId}/draft", $this->marksPayload(1, $g['enrollments'], [1, 1]), ['X-Idempotency-Key' => 'x2'])
            ->assertSessionHas('error', 'attendance.lesson_access_denied');
        // The other teacher's own day does not list the lesson.
        $this->get('/attendance/my?date='.self::LESSON_DATE)->assertInertia(fn ($page) => $page->has('day.lessons', 0));
    }

    #[Test]
    public function students_outside_the_lesson_roster_are_rejected(): void
    {
        $schoolId = $this->createSchool('SCH-AW5', 'Attendance W5');
        $g = $this->seedLessonGraph($schoolId, 1);
        $foreign = $this->createActiveEnrollmentForSchool($schoolId, $g['year_id']);
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $sessionId = $this->openLesson($g);

        $this->post("/attendance/sheets/{$sessionId}/draft", $this->marksPayload(1, [$g['enrollments'][0], (int) $foreign->id], [1, 1]), ['X-Idempotency-Key' => 'f1'])
            ->assertSessionHas('error', 'attendance.student_not_in_roster');
        $this->assertSame(0, DB::table('attendance.records')->where('session_id', $sessionId)->count());
    }

    #[Test]
    public function school_isolation_hides_sheets_of_other_schools(): void
    {
        $schoolA = $this->createSchool('SCH-AW6A', 'Attendance W6A');
        $schoolB = $this->createSchool('SCH-AW6B', 'Attendance W6B');
        $g = $this->seedLessonGraph($schoolA);
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolA);
        $sessionId = $this->openLesson($g);

        $this->actAsAttendanceManager(User::factory()->create(), $schoolB);
        $this->get("/attendance/sheets/{$sessionId}")->assertNotFound();
        $this->post("/attendance/sheets/{$sessionId}/review", ['action' => 'approve', 'version' => 1], ['X-Idempotency-Key' => 'iso'])
            ->assertSessionHas('error');
        $this->assertSame(1, (int) $this->sheet($sessionId)->sheet_status);
    }

    #[Test]
    public function holidays_and_the_policy_window_block_opening(): void
    {
        $schoolId = $this->createSchool('SCH-AW7', 'Attendance W7');
        $g = $this->seedLessonGraph($schoolId);
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);

        Carbon::setTestNow('2026-10-15 03:00:00'); // 06:00 local — before start − 10 min.
        $this->post('/attendance/lessons/open', ['schedule_id' => $g['schedule_id'], 'date' => self::LESSON_DATE], ['X-Idempotency-Key' => 'w1'])
            ->assertSessionHas('error', 'attendance.lesson_window_not_open');

        Carbon::setTestNow(self::DURING_LESSON_UTC);
        DB::table('academic.holidays')->insert([
            'academic_year_id' => $g['year_id'], 'school_id' => $schoolId, 'name' => 'عطلة', 'start_date' => self::LESSON_DATE, 'end_date' => self::LESSON_DATE, 'holiday_type' => 1,
        ]);
        $this->post('/attendance/lessons/open', ['schedule_id' => $g['schedule_id'], 'date' => self::LESSON_DATE], ['X-Idempotency-Key' => 'w2'])
            ->assertSessionHas('error', 'attendance.date_is_holiday');
        $this->assertSame(0, DB::table('attendance.sessions')->where('schedule_id', $g['schedule_id'])->count());
    }

    #[Test]
    public function bulk_approval_reports_stale_items_instead_of_approving_them(): void
    {
        $schoolId = $this->createSchool('SCH-AW8', 'Attendance W8');
        $g = $this->seedLessonGraph($schoolId, 1);
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $sessionId = $this->openLesson($g);
        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(1, $g['enrollments'], [1], true), ['X-Idempotency-Key' => 'b1']);

        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);
        $this->get('/attendance/review?date_from='.self::LESSON_DATE.'&date_to='.self::LESSON_DATE)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('attendance/review')->has('board.data', 1)->where('board.data.0.id', $sessionId));

        $this->post('/attendance/review/bulk-approve', ['items' => [['session_id' => $sessionId, 'version' => 1]]], ['X-Idempotency-Key' => 'bulk-stale'])
            ->assertSessionHas('attendanceResult.failed.0.code', 'attendance.sheet_version_conflict');
        $this->post('/attendance/review/bulk-approve', ['items' => [['session_id' => $sessionId, 'version' => 2]]], ['X-Idempotency-Key' => 'bulk-ok'])
            ->assertSessionHas('attendanceResult.approved', [$sessionId]);
        $this->assertSame(4, (int) $this->sheet($sessionId)->sheet_status);
    }

    #[Test]
    public function late_arrivals_follow_the_policy_and_corrections_are_versioned(): void
    {
        $schoolId = $this->createSchool('SCH-AW9', 'Attendance W9');
        $g = $this->seedLessonGraph($schoolId, 2);
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $sessionId = $this->openLesson($g);
        [$e1, $e2] = $g['enrollments'];

        // Late 3 minutes (≤ 5 grace) counts as present; 20 minutes stays late.
        $this->post("/attendance/sheets/{$sessionId}/draft", ['version' => 1, 'marks' => [
            ['enrollment_id' => $e1, 'status' => 3, 'minutes_late' => 3],
            ['enrollment_id' => $e2, 'status' => 3, 'minutes_late' => 20],
        ]], ['X-Idempotency-Key' => 'late-1'])->assertSessionHas('attendanceResult.normalized', 1);
        $this->assertSame(1, (int) DB::table('attendance.records')->where('session_id', $sessionId)->where('enrollment_id', $e1)->value('status'));
        $this->assertSame(20, (int) DB::table('attendance.records')->where('session_id', $sessionId)->where('enrollment_id', $e2)->value('minutes_late'));

        // Teacher may not record «غياب بعذر» under the default policy.
        $this->post("/attendance/sheets/{$sessionId}/draft", $this->marksPayload(2, [$e1], [4]), ['X-Idempotency-Key' => 'late-2'])
            ->assertSessionHas('error', 'attendance.status_not_allowed');

        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(2, [$e1, $e2], [1, 2], true), ['X-Idempotency-Key' => 'late-3']);
        $manager = User::factory()->create();
        $this->actAsAttendanceManager($manager, $schoolId);
        $this->post("/attendance/sheets/{$sessionId}/review", ['action' => 'approve', 'version' => 3], ['X-Idempotency-Key' => 'late-4']);

        // Approved sheet: only the audited correction path changes a record — and it bumps the version.
        $this->post("/attendance/sheets/{$sessionId}/students/{$g['students'][1]}/correct", [
            'academic_year_id' => $g['year_id'], 'new_status' => 5, 'reason' => 'غادر مبكراً بإذن',
        ], ['X-Idempotency-Key' => 'corr-1'])->assertSessionHas('success', 'flash.attendance.recordCorrected');
        $this->assertSame(5, (int) DB::table('attendance.records')->where('session_id', $sessionId)->where('enrollment_id', $e2)->value('status'));
        $this->assertSame(5, (int) $this->sheet($sessionId)->version);
        $this->assertSame(8, (int) DB::table('attendance.sheet_events')->where('session_id', $sessionId)->orderByDesc('id')->value('event_type'));
    }

    /**
     * @param  array<string, mixed>  $g
     */
    private function openLesson(array $g): int
    {
        $this->post('/attendance/lessons/open', ['schedule_id' => $g['schedule_id'], 'date' => self::LESSON_DATE], ['X-Idempotency-Key' => 'open-'.uniqid()])
            ->assertRedirect();

        return (int) DB::table('attendance.sessions')->where('schedule_id', $g['schedule_id'])->value('id');
    }
}
