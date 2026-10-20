<?php

namespace App\Http\Controllers\Leave;

use App\Application\Leave\Commands\CreateLeaveDelegationCommand;
use App\Application\Leave\Commands\CreateLeaveDelegationHandler;
use App\Application\Leave\Commands\RevokeLeaveDelegationCommand;
use App\Application\Leave\Commands\RevokeLeaveDelegationHandler;
use App\Application\Leave\Queries\GetLeaveBadgesHandler;
use App\Application\Leave\Queries\GetLeaveBadgesQuery;
use App\Application\Leave\Queries\ListLeaveDelegationsHandler;
use App\Application\Leave\Queries\ListLeaveDelegationsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ManageLeaveDelegationRequest;
use App\Http\Support\LeaveRequestContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «التفويض»: an approver lets someone else decide leave for a period. Thin — the rules live in the handlers. */
final class LeaveDelegationController extends Controller
{
    public function __construct(
        private readonly LeaveRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly GetLeaveBadgesHandler $badgeCounts,
    ) {}

    public function index(Request $request, ListLeaveDelegationsHandler $list): Response
    {
        $this->authorize('accessLeave');
        abort_unless($request->user()?->can('holdLeaveApproval') || $request->user()?->can('manageLeaveRequests'), 403);
        $schoolId = $this->context->schoolId();
        $userId = $this->context->userId($request);
        $all = (bool) $request->user()?->can('manageLeaveRequests');

        return Inertia::render('leave/delegations', [
            'badges' => $this->badgeCounts->handle(new GetLeaveBadgesQuery($schoolId, $userId, (bool) $request->user()?->can('approveLeave'))),
            'delegations' => $list->handle(new ListLeaveDelegationsQuery($schoolId, $userId, $all ? null : $userId)),
            'today' => $this->context->today($this->context->academicYearId($request)),
            'user_id' => $userId,
            'abilities' => $this->context->abilities($request),
        ]);
    }

    public function store(ManageLeaveDelegationRequest $request, CreateLeaveDelegationHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreateLeaveDelegationCommand(
            $this->context->schoolId(),
            $this->context->userId($request),
            (int) $request->validated('delegate_user_id'),
            (string) $request->validated('date_from'),
            (string) $request->validated('date_to'),
            $this->context->today($this->context->academicYearId($request)),
            trim((string) $request->header('X-Idempotency-Key')),
        ));
        $this->securityAudit->record(SecurityEventType::HrDataModified, 'leave.web.delegations.store', 'success', $request->user(), 'delegation:'.$result->get('delegation_id'), ['from_idempotency' => $result->fromIdempotencyCache]);

        return back()->with('success', 'flash.leave.delegationCreated');
    }

    public function revoke(ManageLeaveDelegationRequest $request, int $delegation, RevokeLeaveDelegationHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new RevokeLeaveDelegationCommand(
            $this->context->schoolId(),
            $delegation,
            $this->context->userId($request),
            (bool) $request->user()?->can('manageLeaveRequests'),
            trim((string) $request->header('X-Idempotency-Key')),
        ));
        $this->securityAudit->record(SecurityEventType::HrDataModified, 'leave.web.delegations.revoke', 'success', $request->user(), 'delegation:'.$delegation, ['from_idempotency' => $result->fromIdempotencyCache]);

        return back()->with('success', 'flash.leave.delegationRevoked');
    }
}
