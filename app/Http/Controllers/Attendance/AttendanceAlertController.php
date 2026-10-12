<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Commands\UpdateAttendanceAlertCommand;
use App\Application\Attendance\Commands\UpdateAttendanceAlertHandler;
use App\Application\Attendance\Queries\ListAttendanceAlertsHandler;
use App\Application\Attendance\Queries\ListAttendanceAlertsQuery;
use App\Domain\Attendance\Services\AttendanceAlertRules;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\DetectAttendanceAlertsRequest;
use App\Http\Requests\Attendance\UpdateAttendanceAlertRequest;
use App\Http\Support\AttendanceFilterInput;
use App\Http\Support\AttendanceRequestContext;
use App\Infrastructure\Jobs\DetectAttendanceAlertsJob;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «التنبيهات»: rule hits for human review — acknowledge, resolve or dismiss (with a note). */
final class AttendanceAlertController extends Controller
{
    public function __construct(
        private readonly AttendanceRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, ListAttendanceAlertsHandler $handler): Response
    {
        $this->authorize('manageAlerts', AttendanceSessionRecord::class);
        $yearId = $this->context->academicYearId($request);
        $rules = array_values(array_filter((array) $request->query('rule_code', []), static fn ($r): bool => is_string($r) && in_array($r, AttendanceAlertRules::ALL, true)));
        $filters = AttendanceFilterInput::from($request, ['severity'], [])
            + ['status' => AttendanceFilterInput::statuses($request, 'status', [1]), 'rule_code' => $rules];
        $page = max(1, (int) $request->query('page', 1));
        $alerts = $handler->handle(new ListAttendanceAlertsQuery($this->context->schoolId(), $yearId, $filters, $page, AttendanceFilterInput::perPage($request, 'alerts', 25)));

        return Inertia::render('attendance/alerts', [
            'alerts' => $alerts->toArray(),
            'filters' => $filters + ['page' => $page, 'academic_year_id' => $yearId],
            'abilities' => $this->context->abilities($request),
        ]);
    }

    public function update(UpdateAttendanceAlertRequest $request, int $alert, UpdateAttendanceAlertHandler $handler): RedirectResponse
    {
        $handler->handle(new UpdateAttendanceAlertCommand(
            $this->context->schoolId(), $alert, (int) $request->validated('alert_status'), $request->validated('note'),
            (int) $request->user()?->id, $request->idempotencyKey("alert:{$alert}"),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.alert.update', (string) $request->validated('alert_status'), $request->user(), "attendance_alert:{$alert}");

        return back()->with('success', 'flash.attendance.alertUpdated');
    }

    /** Detection runs on the queue — never inside this request. */
    public function detect(DetectAttendanceAlertsRequest $request): RedirectResponse
    {
        DetectAttendanceAlertsJob::dispatch($this->context->schoolId());

        return back()->with('success', 'flash.attendance.alertsQueued');
    }
}
