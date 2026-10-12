<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Attendance\Commands\DetectAttendanceAlertsCommand;
use App\Application\Attendance\Commands\DetectAttendanceAlertsHandler;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Attendance\SeedsAttendanceLessonGraph;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class AttendanceOperationsPostgreSqlTest extends PostgreSqlIntegrationTestCase
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
    public function approved_excuse_turns_absences_into_excused_and_versions_the_sheet(): void
    {
        $schoolId = $this->createSchool('SCH-AO1', 'Attendance O1');
        $g = $this->seedLessonGraph($schoolId, 1);
        $sessionId = $this->submittedSheet($g, [2]);
        $manager = User::factory()->create();
        $this->actAsAttendanceManager($manager, $schoolId);
        $this->post("/attendance/sheets/{$sessionId}/review", ['action' => 'approve', 'version' => 3], ['X-Idempotency-Key' => 'ex-a']);

        // Rejection requires a note; an excuse alone changes nothing.
        $this->post('/attendance/excuses', [
            'enrollment_id' => $g['enrollments'][0], 'date_from' => self::LESSON_DATE, 'date_to' => self::LESSON_DATE, 'excuse_type' => 1, 'reason' => 'مراجعة طبية',
        ], ['X-Idempotency-Key' => 'ex-1'])->assertSessionHas('success', 'flash.attendance.excuseSubmitted');
        $excuseId = (int) DB::table('attendance.excuses')->value('id');
        $this->assertSame(2, (int) DB::table('attendance.records')->where('session_id', $sessionId)->value('status'));
        $this->post("/attendance/excuses/{$excuseId}/decide", ['decision' => 'reject', 'version' => 1])->assertSessionHasErrors('note');

        $this->post("/attendance/excuses/{$excuseId}/decide", ['decision' => 'approve', 'version' => 1], ['X-Idempotency-Key' => 'ex-2'])
            ->assertSessionHas('success', 'flash.attendance.excuseApproved?count=1');
        $record = DB::table('attendance.records')->where('session_id', $sessionId)->first();
        $this->assertSame(4, (int) $record->status);
        $this->assertSame($excuseId, (int) $record->excuse_id);
        $this->assertSame(3, (int) $record->marked_via);
        $this->assertSame(2, (int) DB::table('attendance.excuses')->where('id', $excuseId)->value('status'));
        $this->assertSame(5, (int) $this->sheet($sessionId)->version);
        // A decided excuse cannot be decided again.
        $this->post("/attendance/excuses/{$excuseId}/decide", ['decision' => 'approve', 'version' => 2], ['X-Idempotency-Key' => 'ex-3'])
            ->assertSessionHas('error', 'attendance.excuse_not_pending');

        $this->get('/attendance/excuses?status=')->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('attendance/excuses')->has('excuses.data', 1));
    }

    #[Test]
    public function excuse_for_a_student_of_another_school_is_refused(): void
    {
        $schoolA = $this->createSchool('SCH-AO2A', 'Attendance O2A');
        $schoolB = $this->createSchool('SCH-AO2B', 'Attendance O2B');
        $g = $this->seedLessonGraph($schoolA, 1);
        $this->actAsAttendanceManager(User::factory()->create(), $schoolB);

        $this->post('/attendance/excuses', [
            'enrollment_id' => $g['enrollments'][0], 'date_from' => self::LESSON_DATE, 'date_to' => self::LESSON_DATE, 'excuse_type' => 1, 'reason' => 'x x x',
        ], ['X-Idempotency-Key' => 'ex-x'])->assertSessionHas('error');
        $this->assertSame(0, DB::table('attendance.excuses')->count());
    }

    #[Test]
    public function staff_check_in_needs_a_valid_temporary_code_and_is_recorded_once(): void
    {
        $schoolId = $this->createSchool('SCH-AO3', 'Attendance O3');
        $g = $this->seedLessonGraph($schoolId, 1);
        $manager = User::factory()->create();
        $this->actAsAttendanceManager($manager, $schoolId);

        $issued = $this->post('/attendance/staff/codes', [], ['X-Idempotency-Key' => 'code-1']);
        $code = (string) $issued->getSession()->get('attendanceResult')['code'];
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertFalse(DB::table('attendance.checkin_codes')->where('code_hash', $code)->exists());

        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $wrong = $code === '000000' ? '111111' : '000000';
        $this->post('/attendance/staff/check-in', ['code' => $wrong], ['X-Idempotency-Key' => 'ci-0'])->assertSessionHas('error', 'attendance.checkin_code_invalid');
        $this->post('/attendance/staff/check-in', ['code' => $code], ['X-Idempotency-Key' => 'ci-1'])->assertSessionHas('success', 'flash.attendance.checkedIn');
        $row = DB::table('attendance.staff_records')->where('teacher_id', $g['teacher_id'])->first();
        // 08:10 local vs 07:30 + 10 min grace → late 40 minutes.
        $this->assertSame(3, (int) $row->status);
        $this->assertSame(40, (int) $row->minutes_late);
        $this->assertSame(4, (int) $row->method);
        $this->post('/attendance/staff/check-in', ['code' => $code], ['X-Idempotency-Key' => 'ci-2'])->assertSessionHas('success', 'flash.attendance.checkedOut');
        $this->post('/attendance/staff/check-in', ['code' => $code], ['X-Idempotency-Key' => 'ci-3'])->assertSessionHas('error', 'attendance.already_checked_in_conflict');

        // Expired code.
        Carbon::setTestNow('2026-10-15 06:00:00');
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $this->post('/attendance/staff/check-in', ['code' => $code], ['X-Idempotency-Key' => 'ci-4'])->assertSessionHas('error', 'attendance.checkin_code_invalid');
    }

    #[Test]
    public function staff_corrections_need_the_current_version_and_a_reason_and_absences_list_lessons_needing_cover(): void
    {
        $schoolId = $this->createSchool('SCH-AO4', 'Attendance O4');
        $g = $this->seedLessonGraph($schoolId, 1);
        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);
        $base = ['teacher_id' => $g['teacher_id'], 'work_date' => self::LESSON_DATE];

        $this->post('/attendance/staff', $base + ['attendance_status' => 1, 'check_in' => '07:25'], ['X-Idempotency-Key' => 'st-1'])
            ->assertSessionHas('success', 'flash.attendance.staffRecorded');
        $this->post('/attendance/staff', $base + ['attendance_status' => 2, 'version' => 1], ['X-Idempotency-Key' => 'st-2'])
            ->assertSessionHas('error', 'attendance.reason_required');
        $this->post('/attendance/staff', $base + ['attendance_status' => 2, 'version' => 9, 'reason' => 'x'], ['X-Idempotency-Key' => 'st-3'])
            ->assertSessionHas('error', 'attendance.staff_record_conflict');
        $this->post('/attendance/staff', $base + ['attendance_status' => 2, 'version' => 1, 'reason' => 'لم يحضر'], ['X-Idempotency-Key' => 'st-4'])
            ->assertSessionHas('success', 'flash.attendance.staffCorrected');

        // The timetable is untouched; the absent teacher's lesson is listed for cover.
        $this->get('/attendance/staff?date='.self::LESSON_DATE)->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('attendance/staff')->has('day.cover', 1)->where('day.cover.0.schedule_id', $g['schedule_id']));
        $this->assertSame($g['teacher_id'], (int) DB::table('timetable.schedules')->where('id', $g['schedule_id'])->value('teacher_id'));

        // A teacher cannot record staff attendance.
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $this->post('/attendance/staff', $base + ['attendance_status' => 1], ['X-Idempotency-Key' => 'st-5'])->assertForbidden();
    }

    #[Test]
    public function alert_rules_raise_reviewable_alerts_without_changing_records(): void
    {
        $schoolId = $this->createSchool('SCH-AO5', 'Attendance O5');
        $g = $this->seedLessonGraph($schoolId, 1);
        foreach (['2026-10-11', '2026-10-12', '2026-10-13'] as $i => $date) {
            $periodId = (int) DB::table('timetable.periods')->insertGetId(['school_id' => $schoolId, 'period_number' => 900 + $i, 'start_time' => '09:00', 'end_time' => '09:45', 'period_type' => 1]);
            $sessionId = (int) DB::table('attendance.sessions')->insertGetId([
                'section_id' => $g['section_id'], 'subject_id' => $g['subject_id'], 'academic_year_id' => $g['year_id'], 'session_date' => $date,
                'period_id' => $periodId, 'teacher_id' => $g['teacher_id'], 'status' => 2, 'school_id' => $schoolId, 'sheet_status' => 4, 'created_at' => now(),
            ]);
            DB::table('attendance.records')->insert([
                'session_id' => $sessionId, 'student_id' => $g['students'][0], 'enrollment_id' => $g['enrollments'][0], 'academic_year_id' => $g['year_id'],
                'school_id' => $schoolId, 'attendance_date' => $date, 'status' => 2, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $handler = app(DetectAttendanceAlertsHandler::class);
        $first = $handler->handle(new DetectAttendanceAlertsCommand($schoolId, $g['year_id'], self::LESSON_DATE, 'det-1'));
        $this->assertGreaterThanOrEqual(1, (int) $first->get('created'));
        $this->assertTrue(DB::table('attendance.alerts')->where('rule_code', 'student_absence_count')->where('subject_id', $g['students'][0])->exists());
        // Re-running the same day deduplicates.
        $before = DB::table('attendance.alerts')->count();
        $handler->handle(new DetectAttendanceAlertsCommand($schoolId, $g['year_id'], self::LESSON_DATE, 'det-2'));
        $this->assertSame($before, DB::table('attendance.alerts')->count());
        $this->assertSame(3, DB::table('attendance.records')->where('student_id', $g['students'][0])->where('status', 2)->count());

        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);
        $alertId = (int) DB::table('attendance.alerts')->value('id');
        $this->post("/attendance/alerts/{$alertId}", ['alert_status' => 4], ['X-Idempotency-Key' => 'al-1'])->assertSessionHasErrors('note');
        $this->post("/attendance/alerts/{$alertId}", ['alert_status' => 3, 'note' => 'تم التواصل مع ولي الأمر'], ['X-Idempotency-Key' => 'al-2'])
            ->assertSessionHas('success', 'flash.attendance.alertUpdated');
        $this->get('/attendance/alerts?status=')->assertSuccessful()->assertInertia(fn ($page) => $page->component('attendance/alerts')->has('alerts.data'));
    }

    #[Test]
    public function reports_dashboard_and_export_respect_scope_and_permissions(): void
    {
        $schoolId = $this->createSchool('SCH-AO6', 'Attendance O6');
        $g = $this->seedLessonGraph($schoolId, 2);
        $sessionId = $this->submittedSheet($g, [1, 2]);

        // A teacher has no report access.
        $this->get('/attendance/reports')->assertForbidden();

        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);
        $this->get('/attendance/reports?type=students&date_from='.self::LESSON_DATE.'&date_to='.self::LESSON_DATE)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('attendance/reports')->has('report.rows', 2)->where('report.meta.total', 2)->has('report.formula'));
        $this->get('/attendance/reports?type=subjects&date_from='.self::LESSON_DATE.'&date_to='.self::LESSON_DATE)
            ->assertInertia(fn ($page) => $page->where('report.rows.0.present', 1)->where('report.rows.0.absent', 1)->where('report.rows.0.attendance_rate', 50));

        $export = $this->get('/attendance/reports/export?type=students&date_from='.self::LESSON_DATE.'&date_to='.self::LESSON_DATE);
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('تاريخ الاستخراج', $csv);
        $this->assertStringContainsString('student_code', $csv);

        $this->get('/attendance/dashboard')->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('attendance/dashboard')->where('scope', 'school')->has('dashboard.month_totals'));
        $this->assertSame(1, (int) DB::table('attendance.sessions')->where('id', $sessionId)->value('submission_count'));
    }

    #[Test]
    public function policy_changes_are_versioned_and_never_rewrite_existing_sheets(): void
    {
        $schoolId = $this->createSchool('SCH-AO7', 'Attendance O7');
        $g = $this->seedLessonGraph($schoolId, 1);
        $sessionId = $this->submittedSheet($g, [1]);
        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);
        $payload = [
            'timezone' => 'Asia/Baghdad', 'open_before_minutes' => 15, 'submit_deadline_minutes' => 60, 'edit_window_days' => 1, 'late_grace_minutes' => 10,
            'late_as_absent_minutes' => 30, 'require_complete' => true, 'default_mark' => 1, 'teacher_can_mark_excused' => false, 'absence_alert_count' => 4,
            'absence_alert_window_days' => 30, 'absence_rate_alert_percent' => 25, 'late_alert_count' => 5, 'returned_alert_count' => 3,
            'staff_day_start' => '07:45', 'staff_late_grace_minutes' => 5, 'checkin_code_ttl_minutes' => 15,
        ];
        $this->post('/attendance/settings', $payload, ['X-Idempotency-Key' => 'p1'])->assertSessionHas('success', 'flash.attendance.policySaved');
        $this->post('/attendance/settings', ['late_grace_minutes' => 20] + $payload, ['X-Idempotency-Key' => 'p2'])->assertSessionHas('success');
        $this->post('/attendance/settings', ['late_as_absent_minutes' => 5] + $payload, ['X-Idempotency-Key' => 'p3'])->assertSessionHas('error', 'attendance.policy_invalid');

        $versions = DB::table('attendance.policies')->where('school_id', $schoolId)->orderBy('version_no')->get();
        $this->assertCount(2, $versions);
        $this->assertNotNull($versions[0]->effective_to);
        $this->assertNull($versions[1]->effective_to);
        $this->assertSame(20, (int) $versions[1]->late_grace_minutes);
        $this->assertNull($this->sheet($sessionId)->policy_id);
        $this->assertSame(1, (int) DB::table('attendance.records')->where('session_id', $sessionId)->value('status'));

        // A teacher cannot change the policy.
        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $this->post('/attendance/settings', $payload)->assertForbidden();
    }

    #[Test]
    public function new_tables_are_row_level_secured_and_reject_hard_delete(): void
    {
        foreach (['policies', 'sheet_events', 'excuses', 'staff_records', 'checkin_codes', 'alerts'] as $table) {
            $flags = DB::selectOne("SELECT relrowsecurity, relforcerowsecurity FROM pg_class WHERE oid = 'attendance.{$table}'::regclass");
            $this->assertTrue((bool) $flags->relrowsecurity, "{$table} RLS");
            $this->assertTrue((bool) $flags->relforcerowsecurity, "{$table} FORCE RLS");
        }

        $schoolId = $this->createSchool('SCH-AO8', 'Attendance O8');
        $g = $this->seedLessonGraph($schoolId, 1);
        $sessionId = $this->submittedSheet($g, [1]);
        $this->expectException(QueryException::class);
        DB::table('attendance.sheet_events')->where('session_id', $sessionId)->delete();
    }

    /**
     * @param  array<string, mixed>  $g
     * @param  list<int>  $statuses
     */
    private function submittedSheet(array $g, array $statuses): int
    {
        $this->actAsAttendanceTeacher($g['teacher_user'], $g['school_id']);
        $this->post('/attendance/lessons/open', ['schedule_id' => $g['schedule_id'], 'date' => self::LESSON_DATE], ['X-Idempotency-Key' => 'o-'.uniqid()]);
        $sessionId = (int) DB::table('attendance.sessions')->where('schedule_id', $g['schedule_id'])->value('id');
        $this->post("/attendance/sheets/{$sessionId}/draft", $this->marksPayload(1, $g['enrollments'], $statuses), ['X-Idempotency-Key' => 'd-'.uniqid()]);
        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(2, $g['enrollments'], $statuses, true), ['X-Idempotency-Key' => 's-'.uniqid()])
            ->assertSessionHas('success', 'flash.attendance.sheetSubmitted');

        return $sessionId;
    }
}
