<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Attendance\SeedsAttendanceLessonGraph;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** Exceptional lessons, QR / NFC evidence, teacher account linking, queued exports, excuse documents and risk report. */
final class AttendanceEvidenceAndExportsPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;
    use SeedsAttendanceLessonGraph;

    private const POLICY = [
        'timezone' => 'Asia/Baghdad', 'open_before_minutes' => 15, 'submit_deadline_minutes' => 60, 'edit_window_days' => 1, 'late_grace_minutes' => 10,
        'late_as_absent_minutes' => null, 'require_complete' => true, 'default_mark' => 0, 'teacher_can_mark_excused' => false, 'absence_alert_count' => 3,
        'absence_alert_window_days' => 30, 'absence_rate_alert_percent' => 20, 'late_alert_count' => 5, 'returned_alert_count' => 3,
        'staff_day_start' => '07:30', 'staff_late_grace_minutes' => 10, 'checkin_code_ttl_minutes' => 15,
    ];

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
    public function an_exceptional_lesson_opens_once_with_a_reason_and_only_for_reviewers(): void
    {
        $schoolId = $this->createSchool('SCH-AE1', 'Attendance E1');
        $g = $this->seedLessonGraph($schoolId, 2);
        $payload = ['date' => self::LESSON_DATE, 'section_id' => $g['section_id'], 'subject_id' => $g['subject_id'], 'teacher_id' => $g['teacher_id'], 'period_id' => $g['period_id']];

        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $this->post('/attendance/lessons/exceptional', $payload + ['reason' => 'حصة تعويضية'], ['X-Idempotency-Key' => 'ex-t'])->assertForbidden();

        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);
        $this->post('/attendance/lessons/exceptional', $payload, ['X-Idempotency-Key' => 'ex-0'])->assertSessionHasErrors('reason');
        $response = $this->post('/attendance/lessons/exceptional', $payload + ['reason' => 'حصة تعويضية'], ['X-Idempotency-Key' => 'ex-1']);
        $session = DB::table('attendance.sessions')->where('section_id', $g['section_id'])->whereNull('schedule_id')->first();
        $this->assertNotNull($session);
        $response->assertRedirect("/attendance/sheets/{$session->id}");
        $this->assertSame(3, (int) $session->origin);
        $this->assertSame(1, (int) $session->sheet_status);
        $this->assertStringContainsString('حصة تعويضية', (string) DB::table('attendance.sheet_events')->where('session_id', $session->id)->value('note'));

        $this->post('/attendance/lessons/exceptional', $payload + ['reason' => 'مرة ثانية'], ['X-Idempotency-Key' => 'ex-2'])
            ->assertSessionHas('error', 'attendance.duplicate_lesson_conflict');
        // A subject the section does not study is refused.
        $this->post('/attendance/lessons/exceptional', ['subject_id' => 999999] + $payload + ['reason' => 'خطأ'], ['X-Idempotency-Key' => 'ex-3'])
            ->assertSessionHas('error', 'attendance.exceptional_lesson_invalid');
        $this->assertSame(1, DB::table('attendance.sessions')->where('section_id', $g['section_id'])->whereNull('schedule_id')->count());
    }

    #[Test]
    public function qr_and_nfc_evidence_follow_the_policy_flags(): void
    {
        $schoolId = $this->createSchool('SCH-AE2', 'Attendance E2');
        $g = $this->seedLessonGraph($schoolId, 1);
        $manager = User::factory()->create();
        $this->actAsAttendanceManager($manager, $schoolId);

        // Defaults: QR on, NFC off.
        $issued = $this->post('/attendance/staff/codes', [], ['X-Idempotency-Key' => 'c-1'])->getSession()->get('attendanceResult');
        $this->assertStringContainsString('<svg', (string) $issued['qr_svg']);
        $this->post('/attendance/staff/nfc-token', [], ['X-Idempotency-Key' => 'n-0'])->assertSessionHas('error', 'attendance.evidence_disabled');

        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $this->get('/attendance/staff?code='.$issued['code'].'&via=qr')->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('prefill.code', $issued['code'])->where('prefill.via', 'qr')->where('evidence.nfc_enabled', false));
        $this->post('/attendance/staff/check-in', ['code' => $issued['code'], 'via' => 'qr'], ['X-Idempotency-Key' => 'q-1'])->assertSessionHas('success', 'flash.attendance.checkedIn');
        $this->assertSame(6, (int) DB::table('attendance.staff_records')->where('teacher_id', $g['teacher_id'])->value('method'));

        // Enable NFC, disable QR.
        $this->actAsAttendanceManager($manager, $schoolId);
        $this->post('/attendance/settings', ['qr_enabled' => false, 'nfc_enabled' => true] + self::POLICY, ['X-Idempotency-Key' => 'p-1'])->assertSessionHas('success');
        $token = (string) $this->post('/attendance/staff/nfc-token', ['label' => 'البوابة'], ['X-Idempotency-Key' => 'n-1'])->getSession()->get('attendanceResult')['token'];
        $this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $token);
        $this->assertSame(2, (int) DB::table('attendance.checkin_codes')->where('school_id', $schoolId)->where('label', 'البوابة')->value('kind'));
        $code = (string) $this->post('/attendance/staff/codes', [], ['X-Idempotency-Key' => 'c-2'])->getSession()->get('attendanceResult')['code'];

        $this->actAsAttendanceTeacher($g['teacher_user'], $schoolId);
        $this->post('/attendance/staff/check-in', ['code' => $code, 'via' => 'qr'], ['X-Idempotency-Key' => 'q-2'])->assertSessionHas('error', 'attendance.evidence_disabled');
        // A station token is not a typed code, and a typed code is not a station token.
        $this->post('/attendance/staff/check-in', ['code' => $token, 'via' => 'code'], ['X-Idempotency-Key' => 'q-3'])->assertSessionHasErrors('code');
        $this->post('/attendance/staff/check-in', ['code' => str_repeat('0', 24), 'via' => 'nfc'], ['X-Idempotency-Key' => 'q-4'])->assertSessionHas('error', 'attendance.checkin_code_invalid');
        $this->post('/attendance/staff/check-in', ['code' => $token, 'via' => 'nfc'], ['X-Idempotency-Key' => 'q-5'])->assertSessionHas('success', 'flash.attendance.checkedOut');
        $this->assertFalse(DB::table('attendance.checkin_codes')->where('code_hash', $token)->exists());
    }

    #[Test]
    public function teacher_accounts_are_linked_by_email_by_teacher_managers_only(): void
    {
        $schoolId = $this->createSchool('SCH-AE3', 'Attendance E3');
        $g = $this->seedLessonGraph($schoolId, 1);
        $other = User::factory()->create(['email' => 'teacher.new@example.test']);

        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);
        $this->post("/attendance/staff/teachers/{$g['teacher_id']}/account", ['email' => $other->email], ['X-Idempotency-Key' => 'l-0'])->assertForbidden();

        $admin = User::factory()->create();
        app(SecurityPermissionSeeder::class)->grantTeachersManager($admin, $schoolId);
        $this->actAsAttendanceManager($admin, $schoolId);
        $this->post("/attendance/staff/teachers/{$g['teacher_id']}/account", ['email' => 'nobody@example.test'], ['X-Idempotency-Key' => 'l-1'])
            ->assertSessionHas('error', 'teachers.account_email_unknown');
        $this->post("/attendance/staff/teachers/{$g['teacher_id']}/account", ['email' => $other->email], ['X-Idempotency-Key' => 'l-2'])
            ->assertSessionHas('success', 'flash.attendance.accountLinked');
        $this->assertSame($other->id, (int) DB::table('teachers.teachers')->where('id', $g['teacher_id'])->value('user_id'));
        $this->get('/attendance/staff')->assertInertia(fn ($page) => $page->where('abilities.link_account', true)->where('day.rows.0.account_email', $other->email));

        $this->post("/attendance/staff/teachers/{$g['teacher_id']}/account", ['email' => null], ['X-Idempotency-Key' => 'l-3'])
            ->assertSessionHas('success', 'flash.attendance.accountUnlinked');
        $this->assertNull(DB::table('teachers.teachers')->where('id', $g['teacher_id'])->value('user_id'));
    }

    #[Test]
    public function queued_exports_are_built_in_the_background_and_downloaded_only_by_their_requester(): void
    {
        Storage::fake('local');
        $schoolId = $this->createSchool('SCH-AE4', 'Attendance E4');
        $g = $this->seedLessonGraph($schoolId, 2);
        $this->submitSheet($g, [1, 2]);
        $manager = User::factory()->create();
        $this->actAsAttendanceManager($manager, $schoolId);
        $filters = ['date_from' => self::LESSON_DATE, 'date_to' => self::LESSON_DATE, 'section_id' => [$g['section_id']]];

        $this->post('/attendance/reports/exports', ['type' => 'students', 'format' => 1, 'filters' => $filters], ['X-Idempotency-Key' => 'x-1'])
            ->assertSessionHas('success', 'flash.attendance.exportQueued');
        $this->post('/attendance/reports/exports', ['type' => 'students', 'format' => 2, 'filters' => $filters], ['X-Idempotency-Key' => 'x-2']);
        $this->post('/attendance/reports/exports', ['type' => 'nope', 'format' => 1], ['X-Idempotency-Key' => 'x-3'])->assertSessionHasErrors('type');

        $exports = DB::table('attendance.report_exports')->where('school_id', $schoolId)->orderBy('id')->get();
        $this->assertCount(2, $exports);
        foreach ($exports as $export) {
            $this->assertSame(3, (int) $export->status, (string) $export->error);
            $this->assertSame(2, (int) $export->row_count);
            Storage::disk('local')->assertExists((string) $export->storage_key);
        }
        $this->assertStringStartsWith("\xEF\xBB\xBF", Storage::disk('local')->get((string) $exports[0]->storage_key));
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get((string) $exports[1]->storage_key));
        $this->get('/attendance/reports')->assertInertia(fn ($page) => $page->has('exports', 2));
        $this->get("/attendance/reports/exports/{$exports[0]->id}/download")->assertOk();

        // Another manager of the same school cannot download it.
        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);
        $this->get("/attendance/reports/exports/{$exports[0]->id}/download")->assertNotFound();
    }

    #[Test]
    public function an_excuse_can_carry_an_uploaded_document(): void
    {
        Storage::fake((string) config('sis.documents.disk', 'sis_documents'));
        $schoolId = $this->createSchool('SCH-AE5', 'Attendance E5');
        $g = $this->seedLessonGraph($schoolId, 1);
        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);

        $this->post('/attendance/excuses', [
            'enrollment_id' => $g['enrollments'][0], 'date_from' => self::LESSON_DATE, 'date_to' => self::LESSON_DATE, 'excuse_type' => 1, 'reason' => 'تقرير طبي',
            'document' => UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n%test\n"),
        ], ['X-Idempotency-Key' => 'ed-1'])->assertSessionHas('success', 'flash.attendance.excuseSubmitted');
        $this->assertNotNull(DB::table('attendance.excuses')->where('school_id', $schoolId)->value('document_file_id'));

        $this->post('/attendance/excuses', [
            'enrollment_id' => $g['enrollments'][0], 'date_from' => self::LESSON_DATE, 'date_to' => self::LESSON_DATE, 'excuse_type' => 1, 'reason' => 'ملف غير مسموح',
            'document' => UploadedFile::fake()->create('script.exe', 5, 'application/x-msdownload'),
        ], ['X-Idempotency-Key' => 'ed-2'])->assertSessionHasErrors('document');
    }

    #[Test]
    public function the_risk_report_explains_each_score_and_boards_accept_multi_value_filters(): void
    {
        $schoolId = $this->createSchool('SCH-AE6', 'Attendance E6');
        $g = $this->seedLessonGraph($schoolId, 2);
        $this->submitSheet($g, [2, 1]);
        $this->actAsAttendanceManager(User::factory()->create(), $schoolId);

        $this->get('/attendance/reports?type=risk&date_from='.self::LESSON_DATE.'&date_to='.self::LESSON_DATE)->assertSuccessful()
            ->assertInertia(fn ($page) => $page->where('report.type', 'risk')->has('report.rows', 1)
                ->where('report.rows.0.student_id', $g['students'][0])->where('report.rows.0.reasons', fn ($r) => str_contains((string) $r, 'rate:'))
                ->where('report.rows.0.band', fn ($b) => in_array($b, ['low', 'medium', 'high'], true)));

        $this->get('/attendance/review?teacher_id[]='.$g['teacher_id'].'&teacher_id[]=999999&sheet_status[]=2')->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('attendance/review')->where('filters.teacher_id', [$g['teacher_id'], 999999])->has('board.data', 1));
        $this->get('/attendance?sheet_status=')->assertSuccessful()
            ->assertInertia(fn ($page) => $page->component('attendance/index')->where('mode', 'sessions')->has('board.data', 1));
        $this->get('/attendance/review?teacher_id=999999')->assertInertia(fn ($page) => $page->has('board.data', 0));
    }

    /**
     * @param  array<string, mixed>  $g
     * @param  list<int>  $statuses
     */
    private function submitSheet(array $g, array $statuses): int
    {
        $this->actAsAttendanceTeacher($g['teacher_user'], $g['school_id']);
        $this->post('/attendance/lessons/open', ['schedule_id' => $g['schedule_id'], 'date' => self::LESSON_DATE], ['X-Idempotency-Key' => 'o-'.uniqid()]);
        $sessionId = (int) DB::table('attendance.sessions')->where('schedule_id', $g['schedule_id'])->value('id');
        $this->post("/attendance/sheets/{$sessionId}/submit", $this->marksPayload(1, $g['enrollments'], $statuses, true), ['X-Idempotency-Key' => 's-'.uniqid()])
            ->assertSessionHas('success', 'flash.attendance.sheetSubmitted');

        return $sessionId;
    }
}
