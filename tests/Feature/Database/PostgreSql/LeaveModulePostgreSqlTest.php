<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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
        $this->as($outsider, $this->otherSchoolId)->write("/leave/requests/{$request}/submit", ['version' => 1])->assertNotFound();
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
