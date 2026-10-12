<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Commands\BulkApproveAttendanceSheetsCommand;
use App\Application\Attendance\Commands\BulkApproveAttendanceSheetsHandler;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Queries\GetAttendanceDashboardHandler;
use App\Application\Attendance\Queries\GetAttendanceDashboardQuery;
use App\Application\Attendance\Queries\ListAttendanceSheetsHandler;
use App\Application\Attendance\Queries\ListAttendanceSheetsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\BulkApproveAttendanceSheetsRequest;
use App\Http\Support\AttendanceFilterInput;
use App\Http\Support\AttendanceRequestContext;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\SchoolScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «مراجعة الكشوف» and the role dashboards. */
final class AttendanceReviewController extends Controller
{
    public function __construct(
        private readonly AttendanceRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, ListAttendanceSheetsHandler $handler, AttendanceWorkspaceReadInterface $read): Response
    {
        $this->authorize('reviewSheet', AttendanceSessionRecord::class);
        $schoolId = $this->context->schoolId();
        $yearId = $this->context->academicYearId($request);
        $filters = AttendanceFilterInput::board($request, [2, 3]);
        $page = max(1, (int) $request->query('page', 1));
        $board = $handler->handle(new ListAttendanceSheetsQuery($schoolId, $yearId, $filters, $page, (int) config('attendance.per_page.review', 25)));

        return Inertia::render('attendance/review', [
            'board' => $board->toArray(),
            'filters' => $filters + ['academic_year_id' => $yearId, 'page' => $page],
            'lookups' => $read->lookups($schoolId, $yearId),
            'abilities' => $this->context->abilities($request),
            'mode' => 'review',
        ]);
    }

    public function bulkApprove(BulkApproveAttendanceSheetsRequest $request, BulkApproveAttendanceSheetsHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new BulkApproveAttendanceSheetsCommand(
            $this->context->schoolId(), $this->context->academicYearId($request),
            array_map(static fn (array $i): array => ['session_id' => (int) $i['session_id'], 'version' => (int) $i['version']], (array) $request->validated('items')),
            (int) $request->user()?->id, $request->idempotencyKey('bulk-approve'),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.sheets.bulk_approve', 'approved', $request->user(), 'attendance_sessions', [
            'approved' => count((array) $result->get('approved')), 'failed' => count((array) $result->get('failed')),
        ]);

        return back()->with('success', 'flash.attendance.bulkApproved?approved='.count((array) $result->get('approved')).'&failed='.count((array) $result->get('failed')))
            ->with('attendanceResult', ['action' => 'bulk_approve'] + $result->data);
    }

    public function dashboard(Request $request, GetAttendanceDashboardHandler $handler, SchoolScopeService $schoolScope): Response
    {
        $this->authorize('viewReports', AttendanceSessionRecord::class);
        $user = $request->user();
        assert($user !== null);
        $yearId = $this->context->academicYearId($request);
        $central = $request->query('scope') === 'central' ? array_values(array_map('intval', $schoolScope->allowedSchoolIds($user))) : [];

        $dashboard = $handler->handle(new GetAttendanceDashboardQuery($this->context->schoolId(), $yearId, $central));
        $this->securityAudit->record(SecurityEventType::AttendanceDataAccess, 'attendance.web.dashboard', 'allowed', $user, 'attendance_dashboard', ['central' => count($central)]);

        return Inertia::render('attendance/dashboard', [
            'dashboard' => $dashboard->toArray(),
            'scope' => $central === [] ? 'school' : 'central',
            'can_central' => count($schoolScope->allowedSchoolIds($user)) > 1,
            'filters' => ['academic_year_id' => $yearId],
            'abilities' => $this->context->abilities($request),
        ]);
    }
}
