<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Application\Leave\Commands\RemindPendingLeaveRequestsCommand;
use App\Application\Leave\Commands\RemindPendingLeaveRequestsHandler;
use App\Domain\Academic\Events\HolidayChanged;
use App\Http\Support\LeaveReportCsv;
use App\Infrastructure\Events\HolidayChangedBridgeEvent;
use App\Models\User;
use App\Security\Context\SchoolContextScope;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;
use Tests\Support\Database\PostgreSqlRlsActor;

/**
 * «الإجازات والغياب» on PostgreSQL: types and versioned policies, entitlements, the request lifecycle with two
 * approval levels, the balance ledger (one deduction, one restoration, idempotent retries), overlap and balance
 * rules, self-approval, sensitive masking and attachments, and isolation between two schools (HTTP + real RLS).
 */
final class LeaveModulePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    private int $key = 0;

    private int $schoolId;

    private int $otherSchoolId;

    private int $yearId;

    private int $teacherId;

    private int $employeeId;

    private User $manager;

    private User $approver1;

    private User $approver2;

    private User $teacherUser;

    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();
        $tag = substr(uniqid(), -6);
        $this->schoolId = $this->createSchool('SCH-LV-'.$tag, 'Leave School');
        $this->otherSchoolId = $this->createSchool('SCH-LV2-'.$tag, 'Other School');
        $this->yearId = $this->createAcademicYear('AY-LV-'.$tag);
        // Only this year is current: the request's resolved year is never another suite's.
        DB::table('academic.academic_years')->where('id', '<>', $this->yearId)->update(['is_current' => false]);

        $this->manager = User::factory()->create();
        $this->approver1 = User::factory()->create();
        $this->approver2 = User::factory()->create();
        $this->teacherUser = User::factory()->create();
        $this->viewer = User::factory()->create();
        $seeder = app(SecurityPermissionSeeder::class);
        $seeder->run();
        // Every role before the first request (permissions are cached per user once read).
        $seeder->assignRole($this->manager, 'leave_manager', $this->schoolId);
        $seeder->assignRole($this->manager, 'attendance_manager', $this->schoolId);
        $seeder->assignRole($this->approver1, 'leave_approver', $this->schoolId);
        $seeder->assignRole($this->approver2, 'leave_approver', $this->schoolId);
        $seeder->assignRole($this->teacherUser, 'leave_requester', $this->schoolId);
        $seeder->assignRole($this->viewer, 'leave_viewer', $this->schoolId);

        $this->teacherId = (int) DB::table('teachers.teachers')->insertGetId([
            'employee_code' => 'T-LV-'.$tag, 'first_name' => 'علي', 'last_name' => 'حسن', 'full_name' => 'علي حسن',
            'user_id' => $this->teacherUser->id, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('teachers.teacher_schools')->insert(['teacher_id' => $this->teacherId, 'school_id' => $this->schoolId, 'academic_year_id' => $this->yearId, 'is_primary' => true, 'created_at' => now()]);
        $this->employeeId = (int) DB::table('hr.employees')->insertGetId([
            'employee_number' => 'E-LV-'.$tag, 'first_name' => 'سارة', 'last_name' => 'كريم', 'full_name' => 'سارة كريم',
            'status' => 1, 'effective_from' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('hr.employee_schools')->insert(['employee_id' => $this->employeeId, 'school_id' => $this->schoolId, 'academic_year_id' => $this->yearId, 'is_primary' => true, 'created_at' => now()]);
        // A holiday on Tuesday 2026-11-03 (global): leave never counts it.
        DB::table('academic.holidays')->insert(['academic_year_id' => $this->yearId, 'school_id' => null, 'name' => 'عطلة', 'start_date' => '2026-11-03', 'end_date' => '2026-11-03', 'holiday_type' => 1, 'created_at' => now()]);
    }

    #[Test]
    public function a_request_goes_through_two_levels_deducts_once_and_is_restored_on_cancel(): void
    {
        $annual = $this->annualType(levels: 2, entitlement: 20);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual])->assertSessionHas('success', 'flash.leave.granted?count=2');
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual])->assertSessionHas('success', 'flash.leave.granted?count=0');

        // Self-service: the teacher files for themselves (Sun 1 – Thu 5 November, the Tuesday holiday excluded → 4 days).
        $this->as($this->teacherUser)->write('/leave/requests', $this->requestFields($annual, 1, $this->teacherId, '2026-11-01', '2026-11-05'))
            ->assertSessionHas('success', 'flash.leave.saved');
        $this->as($this->teacherUser)->write('/leave/requests', $this->requestFields($annual, 2, $this->employeeId, '2026-11-01', '2026-11-01'))
            ->assertSessionHas('error', 'leave.not_own_request');
        $request = DB::table('leave.requests')->where('teacher_id', $this->teacherId)->first();
        $this->assertSame(4.0, (float) $request->quantity);
        $this->assertSame(1, (int) $request->status);

        $this->as($this->teacherUser)->write("/leave/requests/{$request->id}/submit", ['version' => $request->version])->assertSessionHas('success', 'flash.leave.submitted');
        $this->assertBalance($annual, available: 16.0, reserved: 4.0);
        $this->as($this->teacherUser)->write("/leave/requests/{$request->id}/decide", ['version' => 3, 'decision' => 'approve'])->assertForbidden();

        $this->as($this->approver1)->write("/leave/requests/{$request->id}/decide", ['version' => 3, 'decision' => 'approve'])->assertSessionHas('success', 'flash.leave.levelApproved');
        $this->assertSame([2, 1], $this->statusOf($request->id));
        $this->as($this->approver1)->write("/leave/requests/{$request->id}/decide", ['version' => 4, 'decision' => 'approve'])->assertSessionHas('error', 'leave.same_approver');
        $this->as($this->approver2)->write("/leave/requests/{$request->id}/decide", ['version' => 3, 'decision' => 'approve'])->assertSessionHas('error', 'leave.version_conflict');

        // The final approval, sent twice with one key (a retry): one deduction only.
        $this->as($this->approver2)->write("/leave/requests/{$request->id}/decide", ['version' => 4, 'decision' => 'approve'], 'retry-approve')->assertSessionHas('success', 'flash.leave.approved');
        $this->as($this->approver2)->write("/leave/requests/{$request->id}/decide", ['version' => 4, 'decision' => 'approve'], 'retry-approve')->assertSessionHas('success', 'flash.leave.approved');
        $this->assertSame([4, 2], $this->statusOf($request->id));
        $this->assertSame(1, DB::table('leave.balance_entries')->where('request_id', $request->id)->where('entry_type', 3)->count());
        $this->assertBalance($annual, available: 16.0, reserved: 0.0);

        $this->as($this->manager)->write("/leave/requests/{$request->id}/cancel", ['version' => 5])->assertSessionHasErrors('note');
        $this->as($this->manager)->write("/leave/requests/{$request->id}/cancel", ['version' => 5, 'note' => 'لم تُستخدم'])->assertSessionHas('success', 'flash.leave.cancelled');
        $this->assertBalance($annual, available: 20.0, reserved: 0.0);
        // Created · Submitted · level 1 · Approved · Cancelled — refused attempts and the idempotent retry left no trace.
        $this->assertSame([1, 3, 4, 5, 10], DB::table('leave.request_events')->where('request_id', $request->id)->orderBy('id')->pluck('event_type')->map(fn ($v) => (int) $v)->all());

        // History is append-only and nothing is ever deleted.
        $this->expectException(QueryException::class);
        DB::table('leave.balance_entries')->where('request_id', $request->id)->update(['quantity' => -1]);
    }

    #[Test]
    public function overlap_balance_and_self_approval_are_refused(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 5);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual])->assertSessionHas('success');
        // The teacher also holds the approver role: still never for their own leave.
        app(SecurityPermissionSeeder::class)->assignRole($this->teacherUser, 'leave_approver', $this->schoolId);

        $first = $this->draft($this->manager, $annual, 1, $this->teacherId, '2026-11-01', '2026-11-02');
        $this->submit($this->manager, $first)->assertSessionHas('success');
        $second = $this->draft($this->manager, $annual, 1, $this->teacherId, '2026-11-02', '2026-11-04');
        $this->submit($this->manager, $second)->assertSessionHas('error', 'leave.overlap');
        $tooLong = $this->draft($this->manager, $annual, 1, $this->teacherId, '2026-11-08', '2026-11-12');
        $this->submit($this->manager, $tooLong)->assertSessionHas('error', 'leave.balance_insufficient');

        $this->as($this->teacherUser)->write("/leave/requests/{$first}/decide", ['version' => $this->versionOf($first), 'decision' => 'approve'])->assertSessionHas('error', 'leave.self_approval');
        $this->as($this->approver1)->write("/leave/requests/{$first}/decide", ['version' => $this->versionOf($first), 'decision' => 'reject'])->assertSessionHas('error', 'leave.note_required');

        // Returned for information → edited → resubmitted → approved.
        $this->as($this->approver1)->write("/leave/requests/{$first}/decide", ['version' => $this->versionOf($first), 'decision' => 'info', 'note' => 'أرفق الأمر الإداري'])->assertSessionHas('success', 'flash.leave.infoRequested');
        $this->as($this->manager)->patchWrite("/leave/requests/{$first}", $this->requestFields($annual, 1, $this->teacherId, '2026-11-01', '2026-11-01') + ['version' => $this->versionOf($first)])->assertSessionHas('success');
        $this->submit($this->manager, $first)->assertSessionHas('success');
        $this->assertSame(8, (int) DB::table('leave.request_events')->where('request_id', $first)->max('event_type'), 'resubmitted');
        $this->as($this->approver1)->write("/leave/requests/{$first}/decide", ['version' => $this->versionOf($first), 'decision' => 'approve'])->assertSessionHas('success', 'flash.leave.approved');
        $this->assertSame(-1.0, (float) DB::table('leave.balance_entries')->where('request_id', $first)->value('quantity'));

        // Withdraw is for undecided requests only.
        $this->as($this->manager)->write("/leave/requests/{$first}/withdraw", ['version' => $this->versionOf($first)])->assertSessionHas('error', 'leave.transition_invalid');
        $this->as($this->manager)->write("/leave/requests/{$second}/withdraw", ['version' => $this->versionOf($second)])->assertSessionHas('success', 'flash.leave.withdrawn');
    }

    #[Test]
    public function sensitive_leave_is_masked_and_its_attachments_stay_in_the_module(): void
    {
        $this->as($this->manager)->write('/leave/types/suggested', [])->assertSessionHas('success', 'flash.leave.suggestedAdded?count=7');
        $sick = (int) DB::table('leave.types')->where('school_id', $this->schoolId)->where('code', 'SICK')->value('id');
        $request = $this->draft($this->manager, $sick, 2, $this->employeeId, '2026-11-08', '2026-11-09', 'تشخيص طبي سري');
        $this->submit($this->manager, $request)->assertSessionHas('error', 'leave.document_required');

        $pdf = UploadedFile::fake()->createWithContent('report.pdf', "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
        $this->as($this->manager)->withHeader('X-Idempotency-Key', 'lv-doc-1')->from('/leave/requests')
            ->post("/leave/requests/{$request}/attachments", ['document' => $pdf])->assertSessionHas('success', 'flash.leave.attached');
        $this->submit($this->manager, $request)->assertSessionHas('success');
        $documentId = (int) DB::table('documents.files')->where('entity_type', 'leave_request')->where('entity_id', $request)->value('id');
        $this->assertSame(4, (int) DB::table('documents.files')->where('id', $documentId)->value('document_type'), 'filed as medical');

        // A viewer without «الحساسة»: dates and status yes, type / reason / attachments no.
        $this->as($this->viewer)->get('/leave/requests?academic_year_id='.$this->yearId.'&open='.$request)->assertOk()->assertInertia(fn ($page) => $page
            ->component('leave/requests')
            ->where('requests.items.0.id', $request)
            ->where('requests.items.0.masked', true)
            ->where('requests.items.0.type_name', null)
            ->where('requests.items.0.reason', null)
            ->reloadOnly('detail', fn ($reload) => $reload->where('detail.masked', true)->where('detail.attachments', [])->where('detail.reason', null)));
        $this->as($this->viewer)->get("/leave/requests/{$request}/attachments/{$documentId}")->assertNotFound();
        $this->as($this->approver1)->get("/leave/requests/{$request}/attachments/{$documentId}")->assertOk();

        // The generic documents API never serves a leave attachment.
        $documentsUser = User::factory()->create();
        app(SecurityPermissionSeeder::class)->assignRole($documentsUser, 'documents_manager', $this->schoolId);
        $this->as($documentsUser)->getJson('/api/v1/documents/'.$documentId)->assertNotFound();
        $this->as($documentsUser)->getJson('/api/v1/documents/'.$documentId.'/content')->assertNotFound();
        $this->as($documentsUser)->getJson('/api/v1/documents?entity_type=leave_request&entity_id='.$request)->assertUnprocessable();
    }

    #[Test]
    public function another_school_sees_and_changes_nothing(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 10);
        $request = $this->draft($this->manager, $annual, 1, $this->teacherId, '2026-11-01', '2026-11-01');
        $outsider = User::factory()->create();
        app(SecurityPermissionSeeder::class)->assignRole($outsider, 'leave_manager', $this->otherSchoolId);
        app(SecurityPermissionSeeder::class)->assignRole($outsider, 'leave_approver', $this->otherSchoolId);

        $this->as($outsider, $this->otherSchoolId)->get('/leave/requests?academic_year_id='.$this->yearId.'&open='.$request)->assertOk()->assertInertia(fn ($page) => $page
            ->where('requests.total', 0)
            ->reloadOnly('detail', fn ($reload) => $reload->where('detail', null)));
        $this->as($outsider, $this->otherSchoolId)->write("/leave/requests/{$request}/submit", ['version' => 1])->assertSessionHas('error', 'leave.request_missing');
        $this->as($outsider, $this->otherSchoolId)->write('/leave/requests', $this->requestFields($annual, 1, $this->teacherId, '2026-11-01', '2026-11-01'))->assertSessionHas('error', 'leave.requester_not_in_school');
        // A school cannot even ask for the other school's school context.
        $this->as($outsider, $this->schoolId)->get('/leave/requests')->assertForbidden();

        // Real RLS (non-superuser role): the other school's rows do not exist.
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $this->otherSchoolId]);
        PostgreSqlRlsActor::become();
        $this->assertSame(0, DB::table('leave.requests')->where('id', $request)->count());
        $this->assertSame(0, DB::table('leave.types')->where('id', $annual)->count());
        PostgreSqlRlsActor::reset();
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $this->schoolId]);
        PostgreSqlRlsActor::become();
        $this->assertSame(1, DB::table('leave.requests')->where('id', $request)->count());
        PostgreSqlRlsActor::reset();
    }

    #[Test]
    public function the_pages_render_with_live_data(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $request = $this->draft($this->manager, $annual, 1, $this->teacherId, now()->toDateString(), now()->toDateString());
        $year = '&academic_year_id='.$this->yearId;

        $this->as($this->manager)->get('/leave?'.$year)->assertOk()->assertInertia(fn ($page) => $page->component('leave/dashboard')->has('dashboard.counts.drafts')->etc());
        $this->as($this->manager)->get('/leave/balances?'.$year)->assertOk()->assertInertia(fn ($page) => $page
            ->component('leave/balances')->where('balances.total', 2)->where('balances.items.0.entitled', 10)->etc());
        $this->as($this->manager)->get('/leave/balances?ledger=1:'.$this->teacherId.$year)->assertOk()->assertInertia(fn ($page) => $page
            ->component('leave/balances')->etc());
        $this->as($this->manager)->get('/leave/types')->assertOk()->assertInertia(fn ($page) => $page->component('leave/types')->has('types', 1)->has('types.0.history', 2)->where('types.0.history.0.effective_to', null)->etc());
        $this->as($this->manager)->get('/leave/calendar?month=2026-11'.$year)->assertOk()->assertInertia(fn ($page) => $page
            ->component('leave/calendar')->where('calendar.from', '2026-11-01')->where('calendar.holidays.0.start_date', '2026-11-03')->etc());
        foreach (['by_person', 'by_type', 'pending', 'approval_time', 'holidays'] as $report) {
            $this->as($this->manager)->get('/leave/reports?report='.$report.$year)->assertOk()->assertInertia(fn ($page) => $page->component('leave/reports')->where('report.kind', $report)->etc());
        }
        $this->as($this->manager)->get('/leave/requests?coverage='.$request.$year)->assertOk()->assertInertia(fn ($page) => $page
            ->component('leave/requests')
            ->reloadOnly('coverage', fn ($reload) => $reload->where('coverage.request_id', $request)->where('coverage.lessons', [])->where('coverage.working_days', 1)));

        // Self-service only: own requests, no reports, no types management.
        $this->as($this->teacherUser)->get('/leave/requests?'.$year)->assertOk()->assertInertia(fn ($page) => $page->where('requests.total', 1)->etc());
        $this->as($this->teacherUser)->get('/leave/reports?'.$year)->assertForbidden();
        $this->as($this->teacherUser)->write('/leave/types', ['code' => 'X1'])->assertForbidden();
    }

    #[Test]
    public function attendance_shows_an_approved_leave_without_writing_a_record(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $request = $this->draft($this->manager, $annual, 1, $this->teacherId, '2026-11-02', '2026-11-02');
        $this->submit($this->manager, $request)->assertSessionHas('success');
        $this->as($this->approver1)->write("/leave/requests/{$request}/decide", ['version' => $this->versionOf($request), 'decision' => 'approve'])->assertSessionHas('success', 'flash.leave.approved');
        $number = (int) DB::table('leave.requests')->where('id', $request)->value('request_no');

        $this->as($this->manager)->get('/attendance/staff?date=2026-11-02&academic_year_id='.$this->yearId)->assertOk()->assertInertia(fn ($page) => $page
            ->component('attendance/staff')
            ->where('day.rows.0.teacher_id', $this->teacherId)
            ->where('day.rows.0.leave_request_no', $number)
            ->where('day.rows.0.record_id', null)
            ->etc());
        $this->as($this->manager)->get('/attendance/staff?date=2026-11-04&academic_year_id='.$this->yearId)->assertOk()->assertInertia(fn ($page) => $page
            ->where('day.rows.0.leave_request_no', null)->etc());
        $this->assertSame(0, DB::table('attendance.staff_records')->where('teacher_id', $this->teacherId)->count(), 'approval writes no attendance');
    }

    #[Test]
    public function two_requests_cannot_spend_the_same_balance_and_the_ledger_refuses_a_second_deduction(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 5);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        // 3 + 3 working days against 5: the second is refused while the first holds its 3 back.
        $first = $this->draft($this->manager, $annual, 1, $this->teacherId, '2026-11-08', '2026-11-10');
        $second = $this->draft($this->manager, $annual, 1, $this->teacherId, '2026-11-15', '2026-11-17');
        $this->submit($this->manager, $first)->assertSessionHas('success');
        $this->submit($this->manager, $second)->assertSessionHas('error', 'leave.balance_insufficient');
        $this->as($this->approver1)->write("/leave/requests/{$first}/decide", ['version' => $this->versionOf($first), 'decision' => 'approve'])->assertSessionHas('success', 'flash.leave.approved');
        // A second approval with a fresh key finds the request already decided.
        $this->as($this->approver2)->write("/leave/requests/{$first}/decide", ['version' => $this->versionOf($first), 'decision' => 'approve'])->assertSessionHas('error', 'leave.transition_invalid');

        // The database itself refuses a second deduction for one request.
        $this->expectException(QueryException::class);
        DB::table('leave.balance_entries')->insert([
            'school_id' => $this->schoolId, 'academic_year_id' => $this->yearId, 'leave_type_id' => $annual, 'requester_kind' => 1,
            'teacher_id' => $this->teacherId, 'entry_type' => 3, 'quantity' => -3, 'request_id' => $first,
        ]);
    }

    #[Test]
    public function notifications_follow_the_lifecycle_and_awaiting_lists_only_what_the_user_may_decide(): void
    {
        $annual = $this->annualType(levels: 2, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $request = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-11-01', '2026-11-02');
        $this->submit($this->teacherUser, $request)->assertSessionHas('success');
        $year = '&academic_year_id='.$this->yearId;
        $count = fn (User $u, int $kind): int => DB::table('leave.notifications')->where('user_id', $u->id)->where('kind', $kind)->count();

        // Submitted: both approvers are asked — never the requester, never the manager (no approve right).
        $this->assertSame([1, 1], [$count($this->approver1, 1), $count($this->approver2, 1)]);
        $this->assertSame(0, DB::table('leave.notifications')->whereIn('user_id', [$this->teacherUser->id, $this->manager->id])->count());
        $this->as($this->approver1)->get('/leave/requests?awaiting=1'.$year)->assertInertia(fn ($page) => $page->where('badges.awaiting', 1)->where('badges.unread', 1)->where('requests.total', 1)->etc());

        // Level 1 by approver 1: the requester hears of the progress; approver 1 no longer has it awaiting, approver 2 still does.
        $this->as($this->approver1)->write("/leave/requests/{$request}/decide", ['version' => $this->versionOf($request), 'decision' => 'approve'])->assertSessionHas('success', 'flash.leave.levelApproved');
        $this->assertSame(1, $count($this->teacherUser, 6));
        $this->assertSame(1, $count($this->approver2, 1), 'no second «awaiting» stacked on the unread one');
        $this->as($this->approver1)->get('/leave/requests?awaiting=1'.$year)->assertInertia(fn ($page) => $page->where('badges.awaiting', 0)->where('requests.total', 0)->etc());
        $this->as($this->approver2)->get('/leave/requests?awaiting=1'.$year)->assertInertia(fn ($page) => $page->where('badges.awaiting', 1)->where('requests.total', 1)->etc());

        // The final approval: the requester is told; nothing awaits anyone.
        $this->as($this->approver2)->write("/leave/requests/{$request}/decide", ['version' => $this->versionOf($request), 'decision' => 'approve'])->assertSessionHas('success', 'flash.leave.approved');
        $this->assertSame(1, $count($this->teacherUser, 2));
        $this->as($this->approver2)->get('/leave'.'?'.ltrim($year, '&'))->assertInertia(fn ($page) => $page->where('badges.awaiting', 0)->etc());

        // The requester's page: own notifications only, no reason or file name in them; read one, then all.
        $this->as($this->teacherUser)->get('/leave/notifications?'.ltrim($year, '&'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('leave/notifications')->where('badges.unread', 2)->where('notifications.total', 2)->where('notifications.items.0.kind', 2)->missing('notifications.items.0.reason')->etc());
        $first = (int) DB::table('leave.notifications')->where('user_id', $this->teacherUser->id)->orderBy('id')->value('id');
        $foreign = (int) DB::table('leave.notifications')->where('user_id', $this->approver1->id)->value('id');
        $this->as($this->teacherUser)->write('/leave/notifications/read', ['ids' => [$first, $foreign]])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('leave.notifications')->where('id', $first)->value('read_at'));
        $this->assertNull(DB::table('leave.notifications')->where('id', $foreign)->value('read_at'), 'a user marks only their own');
        $this->as($this->teacherUser)->write('/leave/notifications/read', [])->assertSessionHasNoErrors();
        $this->as($this->teacherUser)->get('/leave/notifications?unread=1&'.ltrim($year, '&'))->assertInertia(fn ($page) => $page->where('badges.unread', 0)->where('notifications.total', 0)->etc());
        $this->assertNull(DB::table('leave.notifications')->where('user_id', $this->approver2->id)->whereNotNull('read_at')->value('id'), 'nobody else was touched');

        // Rejection and cancellation tell the requester too (a second request).
        $second = $this->draft($this->manager, $annual, 1, $this->teacherId, '2026-11-08', '2026-11-08');
        $this->submit($this->manager, $second)->assertSessionHas('success');
        $this->as($this->approver1)->write("/leave/requests/{$second}/decide", ['version' => $this->versionOf($second), 'decision' => 'reject', 'note' => 'غير ممكن'])->assertSessionHas('success', 'flash.leave.rejected');
        $this->assertSame(1, $count($this->teacherUser, 3));

        // Notifications are only ever read or marked by the signed-in user, and only changed in read_at (database guard).
        $this->expectException(QueryException::class);
        DB::table('leave.notifications')->where('id', $first)->update(['kind' => 5]);
    }

    #[Test]
    public function an_approver_delegates_for_a_period_and_the_delegate_stays_bounded_by_the_delegator(): void
    {
        $delegate = User::factory()->create();
        $outsider = User::factory()->create();
        app(SecurityPermissionSeeder::class)->assignRole($delegate, 'leave_requester', $this->schoolId);
        $annual = $this->annualType(levels: 2, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $request = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-11-01', '2026-11-02');
        $this->submit($this->teacherUser, $request)->assertSessionHas('success');
        $decide = fn (User $u, int $id, string $decision = 'approve') => $this->as($u)->write("/leave/requests/{$id}/decide", ['version' => $this->versionOf($id), 'decision' => $decision]);
        $window = ['date_from' => now()->subDay()->toDateString(), 'date_to' => now()->addDays(10)->toDateString()];

        // Without a delegation the delegate decides nothing; only a holder of the approval right delegates it.
        $decide($delegate, $request)->assertForbidden();
        $this->as($this->manager)->write('/leave/delegations', $window + ['delegate_user_id' => $delegate->id])->assertForbidden();
        $this->as($delegate)->write('/leave/delegations', $window + ['delegate_user_id' => $outsider->id])->assertForbidden();

        // Refusals: oneself, someone with no role in the school, a period in the past, an overlapping duplicate.
        $this->as($this->approver1)->write('/leave/delegations', $window + ['delegate_user_id' => $this->approver1->id])->assertSessionHas('error', 'leave.delegate_invalid');
        $this->as($this->approver1)->write('/leave/delegations', $window + ['delegate_user_id' => $outsider->id])->assertSessionHas('error', 'leave.delegate_invalid');
        $this->as($this->approver1)->write('/leave/delegations', ['date_from' => now()->subDays(9)->toDateString(), 'date_to' => now()->subDays(8)->toDateString(), 'delegate_user_id' => $delegate->id])->assertSessionHas('error', 'leave.delegation_dates_invalid');
        $this->as($this->approver1)->write('/leave/delegations', $window + ['delegate_user_id' => $delegate->id])->assertSessionHas('success', 'flash.leave.delegationCreated');
        $this->as($this->approver1)->write('/leave/delegations', $window + ['delegate_user_id' => $delegate->id])->assertSessionHas('error', 'leave.delegation_overlap');

        // The delegate now sees and decides: level 1 by the delegate, so neither the delegate nor the delegator may pass level 2.
        $this->as($delegate)->get('/leave/requests?academic_year_id='.$this->yearId)->assertOk();
        $decide($delegate, $request)->assertSessionHas('success', 'flash.leave.levelApproved');
        $decide($delegate, $request)->assertSessionHas('error', 'leave.same_approver');
        $decide($this->approver1, $request)->assertSessionHas('error', 'leave.same_approver');
        $decide($this->approver2, $request)->assertSessionHas('success', 'flash.leave.approved');

        // The delegate is asked about new requests while the delegation lasts.
        $second = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-11-08', '2026-11-08');
        $this->submit($this->teacherUser, $second)->assertSessionHas('success');
        $this->assertSame(1, DB::table('leave.notifications')->where('user_id', $delegate->id)->where('request_id', $second)->where('kind', 1)->count());

        // The list: the delegator sees their own; a delegate cannot end it; the delegator can, and the right is gone at once.
        $this->as($this->approver1)->get('/leave/delegations?academic_year_id='.$this->yearId)->assertOk()->assertInertia(fn ($page) => $page
            ->component('leave/delegations')->where('delegations.items.0.delegate_user_id', $delegate->id)->etc());
        $delegation = (int) DB::table('leave.delegations')->where('delegator_user_id', $this->approver1->id)->value('id');
        $this->as($delegate)->write("/leave/delegations/{$delegation}/revoke", [])->assertForbidden();
        $this->as($this->approver2)->write("/leave/delegations/{$delegation}/revoke", [])->assertSessionHas('error', 'leave.delegation_missing');
        $this->as($this->approver1)->write("/leave/delegations/{$delegation}/revoke", [])->assertSessionHas('success', 'flash.leave.delegationRevoked');
        $this->as($this->approver1)->write("/leave/delegations/{$delegation}/revoke", [])->assertSessionHas('error', 'leave.delegation_not_active');
        $decide($delegate, $second)->assertForbidden();
    }

    #[Test]
    public function a_delegation_dies_with_the_delegators_own_right_and_never_lets_anyone_approve_their_own_leave(): void
    {
        $delegate = User::factory()->create();
        app(SecurityPermissionSeeder::class)->assignRole($delegate, 'leave_requester', $this->schoolId);
        $annual = $this->annualType(levels: 1, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $window = ['date_from' => now()->toDateString(), 'date_to' => now()->addDays(5)->toDateString(), 'delegate_user_id' => $delegate->id];
        $this->as($this->approver1)->write('/leave/delegations', $window)->assertSessionHas('success');

        // The delegator's approval role ends: the delegation gives nothing any more.
        $request = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-11-01', '2026-11-01');
        $this->submit($this->teacherUser, $request)->assertSessionHas('success');
        DB::table('security.user_roles')->where('user_id', $this->approver1->id)->update(['effective_to' => now()->subDay()->toDateString()]);
        Cache::forget('security.permissions.user.'.$this->approver1->id);
        $this->as($delegate)->write("/leave/requests/{$request}/decide", ['version' => $this->versionOf($request), 'decision' => 'approve'])->assertForbidden();
    }

    #[Test]
    public function waiting_requests_are_reminded_once_per_round_and_escalated_to_the_managers(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $request = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-11-01', '2026-11-02');
        $this->submit($this->teacherUser, $request)->assertSessionHas('success');
        $count = fn (User $u, int $kind): int => DB::table('leave.notifications')->where('user_id', $u->id)->where('request_id', $request)->where('kind', $kind)->count();
        $sweep = function (int $afterDays): array {
            $result = app(SchoolContextScope::class)->run($this->schoolId, fn () => app(RemindPendingLeaveRequestsHandler::class)->handle(
                new RemindPendingLeaveRequestsCommand($this->schoolId, 2, 5, new \DateTimeImmutable("+{$afterDays} days")),
            ));

            return ['reminded' => $result->get('reminded'), 'escalated' => $result->get('escalated')];
        };

        // Too early: nobody is told.
        $this->assertSame(['reminded' => 0, 'escalated' => 0], $sweep(1));
        // After two days the approvers (not the requester, not the manager) are reminded — once, however often it runs.
        $this->assertSame(['reminded' => 2, 'escalated' => 0], $sweep(3));
        $this->assertSame(['reminded' => 0, 'escalated' => 0], $sweep(3));
        $this->assertSame([1, 1, 0, 0], [$count($this->approver1, 8), $count($this->approver2, 8), $count($this->teacherUser, 8), $count($this->manager, 8)]);
        // After five days the managers are told as well — once.
        $this->assertSame(['reminded' => 0, 'escalated' => 1], $sweep(6));
        $this->assertSame(['reminded' => 0, 'escalated' => 0], $sweep(6));
        $this->assertSame([1, 0, 0], [$count($this->manager, 9), $count($this->approver1, 9), $count($this->teacherUser, 9)]);

        // A decided request is left alone.
        $this->as($this->approver1)->write("/leave/requests/{$request}/decide", ['version' => $this->versionOf($request), 'decision' => 'approve'])->assertSessionHas('success');
        $this->assertSame(['reminded' => 0, 'escalated' => 0], $sweep(30));
    }

    #[Test]
    public function a_holiday_change_tells_the_managers_which_open_requests_it_touches_and_rewrites_nothing(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $touched = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-11-08', '2026-11-12');
        $apart = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-12-06', '2026-12-06');
        $before = DB::table('leave.requests')->where('id', $touched)->first(['quantity', 'version']);
        $holiday = (int) DB::table('academic.holidays')->insertGetId([
            'academic_year_id' => $this->yearId, 'school_id' => $this->schoolId, 'name' => 'يوم مدرسي', 'start_date' => '2026-11-10', 'end_date' => '2026-11-10', 'holiday_type' => 2, 'created_at' => now(),
        ]);
        $event = new HolidayChangedBridgeEvent(new HolidayChanged($holiday, $this->schoolId, 'created', new \DateTimeImmutable));

        Event::dispatch($event);
        Event::dispatch($event);

        $notices = fn (int $request) => DB::table('leave.notifications')->where('request_id', $request)->where('kind', 7);
        $this->assertSame(1, $notices($touched)->where('user_id', $this->manager->id)->count(), 'told once, an unread notice is not stacked');
        $this->assertSame(0, $notices($touched)->where('user_id', $this->teacherUser->id)->count());
        $this->assertSame(0, $notices($apart)->count());
        $after = DB::table('leave.requests')->where('id', $touched)->first(['quantity', 'version']);
        $this->assertEquals($before, $after, 'the request keeps its duration and version');
        // A holiday that no longer exists tells nobody.
        Event::dispatch(new HolidayChangedBridgeEvent(new HolidayChanged(999999999, $this->schoolId, 'updated', new \DateTimeImmutable)));
        $this->assertSame(1, DB::table('leave.notifications')->where('kind', 7)->count());
    }

    #[Test]
    public function unused_balance_is_carried_into_the_next_year_once_per_person(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $request = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-11-01', '2026-11-02');
        $this->submit($this->teacherUser, $request)->assertSessionHas('success');
        $this->as($this->approver1)->write("/leave/requests/{$request}/decide", ['version' => $this->versionOf($request), 'decision' => 'approve'])->assertSessionHas('success', 'flash.leave.approved');

        $next = $this->createAcademicYear('AY-LV-NEXT-'.substr(uniqid(), -6));
        DB::table('academic.academic_years')->where('id', $next)->update(['start_date' => '2027-09-01', 'end_date' => '2028-06-30', 'is_current' => false]);
        DB::table('teachers.teacher_schools')->insert(['teacher_id' => $this->teacherId, 'school_id' => $this->schoolId, 'academic_year_id' => $next, 'is_primary' => true, 'created_at' => now()]);
        DB::table('hr.employee_schools')->insert(['employee_id' => $this->employeeId, 'school_id' => $this->schoolId, 'academic_year_id' => $next, 'is_primary' => true, 'created_at' => now()]);
        $carried = fn (int $kind, int $person) => (float) DB::table('leave.balance_entries')->where('academic_year_id', $next)->where('entry_type', 2)
            ->where($kind === 1 ? 'teacher_id' : 'employee_id', $person)->sum('quantity');
        $carry = fn (array $extra = []) => $this->as($this->manager)->write('/leave/balances/carry-over', ['academic_year_id' => $next, 'from_year_id' => $this->yearId, 'leave_type_id' => $annual] + $extra);

        // Refused: a later year as the source, and a rights-less user.
        $this->as($this->manager)->write('/leave/balances/carry-over', ['academic_year_id' => $this->yearId, 'from_year_id' => $next, 'leave_type_id' => $annual])->assertSessionHas('error', 'leave.carry_over_invalid');
        $this->as($this->approver1)->write('/leave/balances/carry-over', ['academic_year_id' => $next, 'from_year_id' => $this->yearId, 'leave_type_id' => $annual])->assertForbidden();

        $carry(['cap' => 5])->assertSessionHas('success', 'flash.leave.carriedOver?count=2');
        $this->assertSame([5.0, 5.0], [$carried(1, $this->teacherId), $carried(2, $this->employeeId)], 'teacher had 8 left, employee 10 — both capped at 5');
        $carry(['cap' => 5])->assertSessionHas('success', 'flash.leave.carriedOver?count=0');
        $this->assertSame([5.0, 5.0], [$carried(1, $this->teacherId), $carried(2, $this->employeeId)], 'a second run adds nothing');
        $this->assertSame(0, DB::table('leave.balance_entries')->where('academic_year_id', $this->yearId)->where('entry_type', 2)->count(), 'the earlier year is untouched');
    }

    #[Test]
    public function a_report_downloads_as_csv_with_the_masking_of_the_page_and_safe_cells(): void
    {
        $annual = $this->annualType(levels: 1, entitlement: 10);
        $this->as($this->manager)->write('/leave/balances/grant', ['leave_type_id' => $annual]);
        $request = $this->draft($this->teacherUser, $annual, 1, $this->teacherId, '2026-11-01', '2026-11-02');
        $this->submit($this->teacherUser, $request)->assertSessionHas('success');

        $response = $this->as($this->manager)->get('/leave/reports/export?report=pending&academic_year_id='.$this->yearId);
        $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body);
        $this->assertStringContainsString('رقم الطلب', $body);
        $this->assertStringContainsString('علي حسن', $body);
        $this->as($this->manager)->get('/leave/reports/export?report=nope&academic_year_id='.$this->yearId)->assertOk();
        // Those without the reports right get nothing.
        $this->as($this->teacherUser)->get('/leave/reports/export?report=pending&academic_year_id='.$this->yearId)->assertForbidden();

        $unsafe = LeaveReportCsv::build('holidays', [['name' => '=1+1', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01', 'holiday_type' => 1, 'global' => true]]);
        $this->assertStringContainsString("'=1+1", $unsafe, 'a cell starting with = is not left to run as a formula');
    }

    private function annualType(int $levels, float $entitlement): int
    {
        $this->as($this->manager)->write('/leave/types', [
            'code' => 'ANNUAL', 'name_ar' => 'إجازة اعتيادية', 'applies_to' => 3, 'unit' => 1, 'is_sensitive' => false,
            'day_basis' => 1, 'requires_document' => false, 'deducts_balance' => true, 'approval_levels' => 1, 'max_per_request' => null, 'annual_entitlement' => null,
        ])->assertSessionHas('success', 'flash.leave.typeSaved');
        $type = (int) DB::table('leave.types')->where('school_id', $this->schoolId)->where('code', 'ANNUAL')->value('id');
        // Rules change by publishing a new version; the first one is kept.
        $this->as($this->manager)->write("/leave/types/{$type}/policy", [
            'day_basis' => 1, 'requires_document' => false, 'deducts_balance' => true, 'approval_levels' => $levels, 'max_per_request' => null, 'annual_entitlement' => $entitlement,
        ])->assertSessionHas('success', 'flash.leave.policyPublished');
        $this->assertSame(2, DB::table('leave.policies')->where('leave_type_id', $type)->count());
        $this->assertSame(1, DB::table('leave.policies')->where('leave_type_id', $type)->whereNull('effective_to')->count());

        return $type;
    }

    /** @return array<string, mixed> */
    private function requestFields(int $type, int $kind, int $person, string $from, string $to, string $reason = 'سفر عائلي'): array
    {
        return ['academic_year_id' => $this->yearId, 'requester_kind' => $kind, 'person_id' => $person, 'leave_type_id' => $type, 'date_from' => $from, 'date_to' => $to, 'reason' => $reason];
    }

    private function draft(User $user, int $type, int $kind, int $person, string $from, string $to, string $reason = 'سفر عائلي'): int
    {
        $this->as($user)->write('/leave/requests', $this->requestFields($type, $kind, $person, $from, $to, $reason))->assertSessionHas('success', 'flash.leave.saved');

        return (int) DB::table('leave.requests')->where('school_id', $this->schoolId)->max('id');
    }

    private function submit(User $user, int $request): TestResponse
    {
        return $this->as($user)->write("/leave/requests/{$request}/submit", ['version' => $this->versionOf($request)]);
    }

    private function versionOf(int $request): int
    {
        return (int) DB::table('leave.requests')->where('id', $request)->value('version');
    }

    /** @return array{0: int, 1: int} status, approved levels */
    private function statusOf(int $request): array
    {
        $row = DB::table('leave.requests')->where('id', $request)->first(['status', 'approved_levels']);

        return [(int) $row->status, (int) $row->approved_levels];
    }

    private function assertBalance(int $type, float $available, float $reserved): void
    {
        $this->as($this->manager)->get('/leave/balances?academic_year_id='.$this->yearId.'&kind=1&type[]='.$type)->assertOk()->assertInertia(fn ($page) => $page
            ->where('balances.items.0.available', fn ($v): bool => (float) $v === $available)
            ->where('balances.items.0.reserved', fn ($v): bool => (float) $v === $reserved)
            ->etc());
    }

    private function as(User $user, ?int $schoolId = null): static
    {
        $this->actingAs($user);

        return $this->withSession(['current_school_id' => $schoolId ?? $this->schoolId])->withHeader('X-School-Id', (string) ($schoolId ?? $this->schoolId));
    }

    /** @param  array<string, mixed>  $data */
    private function write(string $url, array $data, ?string $key = null): TestResponse
    {
        return $this->from('/leave/requests')->withHeader('X-Idempotency-Key', $key ?? 'lv-'.(++$this->key))->post($url, $data);
    }

    /** @param  array<string, mixed>  $data */
    private function patchWrite(string $url, array $data): TestResponse
    {
        return $this->from('/leave/requests')->withHeader('X-Idempotency-Key', 'lv-'.(++$this->key))->patch($url, $data);
    }
}
