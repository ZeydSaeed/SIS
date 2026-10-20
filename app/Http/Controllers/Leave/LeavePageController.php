<?php

namespace App\Http\Controllers\Leave;

use App\Application\Documents\Queries\GetDocumentContentHandler;
use App\Application\Documents\Queries\GetDocumentContentQuery;
use App\Application\Leave\Queries\GetLeaveBadgesHandler;
use App\Application\Leave\Queries\GetLeaveBadgesQuery;
use App\Application\Leave\Queries\GetLeaveCalendarHandler;
use App\Application\Leave\Queries\GetLeaveCalendarQuery;
use App\Application\Leave\Queries\GetLeaveCoverageHandler;
use App\Application\Leave\Queries\GetLeaveCoverageQuery;
use App\Application\Leave\Queries\GetLeaveDashboardHandler;
use App\Application\Leave\Queries\GetLeaveDashboardQuery;
use App\Application\Leave\Queries\GetLeaveLedgerHandler;
use App\Application\Leave\Queries\GetLeaveLedgerQuery;
use App\Application\Leave\Queries\GetLeaveReportHandler;
use App\Application\Leave\Queries\GetLeaveReportQuery;
use App\Application\Leave\Queries\GetLeaveRequestHandler;
use App\Application\Leave\Queries\GetLeaveRequestQuery;
use App\Application\Leave\Queries\ListLeaveBalancesHandler;
use App\Application\Leave\Queries\ListLeaveBalancesQuery;
use App\Application\Leave\Queries\ListLeaveNotificationsHandler;
use App\Application\Leave\Queries\ListLeaveNotificationsQuery;
use App\Application\Leave\Queries\ListLeaveRequestsHandler;
use App\Application\Leave\Queries\ListLeaveRequestsQuery;
use App\Application\Leave\Queries\ListLeaveStaffHandler;
use App\Application\Leave\Queries\ListLeaveStaffQuery;
use App\Application\Leave\Queries\ListLeaveTypesHandler;
use App\Application\Leave\Queries\ListLeaveTypesQuery;
use App\Application\Leave\Queries\SuggestLeaveSubstitutesHandler;
use App\Application\Leave\Queries\SuggestLeaveSubstitutesQuery;
use App\Http\Controllers\Controller;
use App\Http\Support\LeaveListInput;
use App\Http\Support\LeaveRequestContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «الإجازات والغياب» pages. Thin: authorizes, reads through the query handlers (which scope and mask), renders.
 * Details, coverage and substitute suggestions are optional props read only when the page asks for them.
 */
final class LeavePageController extends Controller
{
    public function __construct(
        private readonly LeaveRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly GetLeaveBadgesHandler $badgeCounts,
    ) {}

    public function dashboard(Request $request, GetLeaveDashboardHandler $handler): Response
    {
        $this->authorize('accessLeave');
        $yearId = $this->context->academicYearId($request);
        $today = $this->context->today($yearId);

        return Inertia::render('leave/dashboard', [
            'badges' => $this->badges($request),
            'dashboard' => $handler->handle(new GetLeaveDashboardQuery($this->context->schoolId(), $yearId, $today, $this->context->viewer($request))),
            'today' => $today,
            'filters' => ['academic_year_id' => $yearId],
            'abilities' => $this->context->abilities($request),
        ]);
    }

    /** The module's overview, the first page of «الإجازات والعطل»: the request counts as cards (same data as the dashboard). */
    public function overview(Request $request, GetLeaveDashboardHandler $handler): Response
    {
        $this->authorize('accessLeave');
        $yearId = $this->context->academicYearId($request);
        $today = $this->context->today($yearId);

        return Inertia::render('leave/overview', [
            'badges' => $this->badges($request),
            'dashboard' => $handler->handle(new GetLeaveDashboardQuery($this->context->schoolId(), $yearId, $today, $this->context->viewer($request))),
            'today' => $today,
            'filters' => ['academic_year_id' => $yearId],
            'abilities' => $this->context->abilities($request),
        ]);
    }

    public function requests(
        Request $request,
        ListLeaveRequestsHandler $list,
        ListLeaveTypesHandler $types,
        ListLeaveStaffHandler $staff,
        GetLeaveRequestHandler $detail,
        GetLeaveCoverageHandler $coverage,
        SuggestLeaveSubstitutesHandler $substitutes,
    ): Response {
        $this->authorize('accessLeave');
        $schoolId = $this->context->schoolId();
        $yearId = $this->context->academicYearId($request);
        $viewer = $this->context->viewer($request);
        $filters = LeaveListInput::requests($request, $yearId, $request->user()?->can('approveLeave') ? $viewer->userId : null);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = LeaveListInput::perPage($request);
        $this->securityAudit->record(SecurityEventType::HrDataAccess, 'leave.web.requests', 'viewed', $request->user(), 'school:'.$schoolId, []);

        return Inertia::render('leave/requests', [
            'badges' => $this->badges($request),
            'requests' => $list->handle(new ListLeaveRequestsQuery($schoolId, $viewer, $filters, $page, $perPage)) + ['page' => $page, 'per_page' => $perPage],
            'types' => $types->handle(new ListLeaveTypesQuery($schoolId)),
            'staff' => $staff->handle(new ListLeaveStaffQuery($schoolId, $yearId, $viewer, (bool) $request->user()?->can('manageLeaveRequests'))),
            'filters' => LeaveListInput::echo($request, $yearId),
            'abilities' => $this->context->abilities($request),
            'detail' => Inertia::optional(fn () => $request->filled('open')
                ? $detail->handle(new GetLeaveRequestQuery($schoolId, (int) $request->query('open'), $viewer)) : null),
            'coverage' => Inertia::optional(fn () => $request->filled('coverage')
                ? $coverage->handle(new GetLeaveCoverageQuery($schoolId, (int) $request->query('coverage'), $viewer, max(1, (int) $request->query('coverage_page', 1)))) : null),
            'substitutes' => Inertia::optional(fn () => $request->filled('substitute') && $viewer->seesAll
                ? $substitutes->handle(new SuggestLeaveSubstitutesQuery($schoolId, $yearId, (int) $request->query('substitute'), (string) $request->query('date', ''))) : null),
        ]);
    }

    public function balances(Request $request, ListLeaveBalancesHandler $list, ListLeaveTypesHandler $types, ListLeaveStaffHandler $staff, GetLeaveLedgerHandler $ledger): Response
    {
        $this->authorize('accessLeave');
        $schoolId = $this->context->schoolId();
        $yearId = $this->context->academicYearId($request);
        $viewer = $this->context->viewer($request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = LeaveListInput::perPage($request);
        [$ledgerKind, $ledgerPerson] = LeaveListInput::person((string) $request->query('ledger', ''));

        return Inertia::render('leave/balances', [
            'badges' => $this->badges($request),
            'balances' => $list->handle(new ListLeaveBalancesQuery($schoolId, $yearId, $viewer, LeaveListInput::balances($request), $page, $perPage)) + ['page' => $page, 'per_page' => $perPage],
            'types' => $types->handle(new ListLeaveTypesQuery($schoolId)),
            'staff' => $request->user()?->can('adjustLeaveBalances') ? $staff->handle(new ListLeaveStaffQuery($schoolId, $yearId, $viewer, true)) : [],
            'filters' => LeaveListInput::echo($request, $yearId),
            'abilities' => $this->context->abilities($request),
            'ledger' => Inertia::optional(fn () => $ledgerKind === null ? null : $ledger->handle(new GetLeaveLedgerQuery(
                $schoolId, $yearId, $ledgerKind, (int) $ledgerPerson, $request->filled('ledger_type') ? (int) $request->query('ledger_type') : null, $viewer,
            ))),
        ]);
    }

    public function types(Request $request, ListLeaveTypesHandler $types): Response
    {
        $this->authorize('accessLeave');

        return Inertia::render('leave/types', [
            'badges' => $this->badges($request),
            'types' => $types->handle(new ListLeaveTypesQuery($this->context->schoolId(), true)),
            'abilities' => $this->context->abilities($request),
        ]);
    }

    public function calendar(Request $request, GetLeaveCalendarHandler $calendar, ListLeaveTypesHandler $types): Response
    {
        $this->authorize('accessLeave');
        $schoolId = $this->context->schoolId();
        $yearId = $this->context->academicYearId($request);
        [$from, $to] = LeaveListInput::calendarWindow($request);

        return Inertia::render('leave/calendar', [
            'badges' => $this->badges($request),
            'calendar' => $calendar->handle(new GetLeaveCalendarQuery($schoolId, $yearId, $from, $to, $this->context->viewer($request), LeaveListInput::calendar($request))),
            'types' => $types->handle(new ListLeaveTypesQuery($schoolId)),
            'filters' => LeaveListInput::echo($request, $yearId) + [
                'month' => substr(LeaveListInput::month($request), 0, 7),
                'view' => LeaveListInput::calendarView($request),
                'layers' => LeaveListInput::calendar($request)['layers'],
            ],
            'abilities' => $this->context->abilities($request),
        ]);
    }

    public function reports(Request $request, GetLeaveReportHandler $report, ListLeaveTypesHandler $types): Response
    {
        $this->authorize('viewLeaveReports');
        $schoolId = $this->context->schoolId();
        $yearId = $this->context->academicYearId($request);
        $kind = in_array($request->query('report'), GetLeaveReportQuery::KINDS, true) ? (string) $request->query('report') : 'by_person';
        $this->securityAudit->record(SecurityEventType::HrDataAccess, 'leave.web.reports', 'viewed', $request->user(), 'school:'.$schoolId, ['report' => $kind]);

        return Inertia::render('leave/reports', [
            'badges' => $this->badges($request),
            'report' => ['kind' => $kind, 'rows' => $report->handle(new GetLeaveReportQuery($schoolId, $yearId, $kind, LeaveListInput::report($request), $this->context->viewer($request)))],
            'types' => $types->handle(new ListLeaveTypesQuery($schoolId)),
            'filters' => LeaveListInput::echo($request, $yearId) + ['report' => $kind],
            'abilities' => $this->context->abilities($request),
        ]);
    }

    /** The two counters of the leave rail: unread notifications and requests awaiting this user's decision. */
    private function badges(Request $request): array
    {
        return $this->badgeCounts->handle(new GetLeaveBadgesQuery($this->context->schoolId(), $this->context->userId($request), (bool) $request->user()?->can('approveLeave')));
    }

    /** «الإشعارات»: the user's own notifications; opening a notification opens its request in «طلبات الإجازات». */
    public function notifications(Request $request, ListLeaveNotificationsHandler $list): Response
    {
        $this->authorize('accessLeave');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = LeaveListInput::perPage($request);
        $unreadOnly = $request->boolean('unread');

        return Inertia::render('leave/notifications', [
            'badges' => $this->badges($request),
            'notifications' => $list->handle(new ListLeaveNotificationsQuery($this->context->schoolId(), $this->context->userId($request), $unreadOnly, $page, $perPage)) + ['page' => $page, 'per_page' => $perPage],
            'filters' => ['unread' => $unreadOnly, 'academic_year_id' => $this->context->academicYearId($request)],
            'abilities' => $this->context->abilities($request),
        ]);
    }

    /** An attachment of a request the viewer may see in full (a masked sensitive request lists none) — else 404. */
    public function attachment(Request $request, int $leaveRequest, int $document, GetLeaveRequestHandler $detail, GetDocumentContentHandler $content): HttpResponse
    {
        $this->authorize('accessLeave');
        $schoolId = $this->context->schoolId();
        $row = $detail->handle(new GetLeaveRequestQuery($schoolId, $leaveRequest, $this->context->viewer($request)));
        $listed = $row !== null && in_array($document, array_column($row['attachments'], 'id'), true);
        $file = $listed ? $content->handle(new GetDocumentContentQuery($schoolId, $document)) : null;
        abort_if($file === null, 404);
        $this->securityAudit->record(SecurityEventType::HrDataAccess, 'leave.web.attachment', 'downloaded', $request->user(), 'leave_request:'.$leaveRequest, ['document_id' => $document]);

        return response($file->contents, 200, [
            'Content-Type' => $file->mimeType,
            'Content-Disposition' => 'attachment; filename="document-'.$document.'.'.(pathinfo($file->fileName, PATHINFO_EXTENSION) ?: 'bin').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
