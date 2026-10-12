<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Commands\SaveAttendancePolicyCommand;
use App\Application\Attendance\Commands\SaveAttendancePolicyHandler;
use App\Application\Attendance\Queries\GetAttendancePolicyHandler;
use App\Application\Attendance\Queries\GetAttendancePolicyQuery;
use App\Domain\Attendance\ValueObjects\AttendancePolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\SaveAttendancePolicyRequest;
use App\Http\Support\AttendanceRequestContext;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «إعدادات الحضور»: the school year's attendance policy (each save = a new version; history kept). */
final class AttendancePolicyController extends Controller
{
    public function __construct(
        private readonly AttendanceRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function edit(Request $request, GetAttendancePolicyHandler $handler): Response
    {
        $this->authorize('viewAny', AttendanceSessionRecord::class);
        $yearId = $this->context->academicYearId($request);

        return Inertia::render('attendance/settings', [
            'settings' => $handler->handle(new GetAttendancePolicyQuery($this->context->schoolId(), $yearId))->toArray(),
            'filters' => ['academic_year_id' => $yearId],
            'abilities' => $this->context->abilities($request),
        ]);
    }

    public function update(SaveAttendancePolicyRequest $request, SaveAttendancePolicyHandler $handler): RedirectResponse
    {
        $v = $request->validated();
        $result = $handler->handle(new SaveAttendancePolicyCommand(
            $this->context->schoolId(), $this->context->academicYearId($request),
            new AttendancePolicy(
                id: null,
                timezone: (string) $v['timezone'],
                openBeforeMinutes: (int) $v['open_before_minutes'],
                submitDeadlineMinutes: (int) $v['submit_deadline_minutes'],
                editWindowDays: (int) $v['edit_window_days'],
                lateGraceMinutes: (int) $v['late_grace_minutes'],
                lateAsAbsentMinutes: isset($v['late_as_absent_minutes']) ? (int) $v['late_as_absent_minutes'] : null,
                requireComplete: (bool) $v['require_complete'],
                defaultMark: (int) $v['default_mark'],
                teacherCanMarkExcused: (bool) $v['teacher_can_mark_excused'],
                absenceAlertCount: (int) $v['absence_alert_count'],
                absenceAlertWindowDays: (int) $v['absence_alert_window_days'],
                absenceRateAlertPercent: (int) $v['absence_rate_alert_percent'],
                lateAlertCount: (int) $v['late_alert_count'],
                returnedAlertCount: (int) $v['returned_alert_count'],
                staffDayStart: (string) $v['staff_day_start'],
                staffLateGraceMinutes: (int) $v['staff_late_grace_minutes'],
                checkinCodeTtlMinutes: (int) $v['checkin_code_ttl_minutes'],
                qrEnabled: (bool) ($v['qr_enabled'] ?? true),
                nfcEnabled: (bool) ($v['nfc_enabled'] ?? false),
            ),
            (int) $request->user()?->id, $request->idempotencyKey('policy'),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.policy.update', 'saved', $request->user(), 'attendance_policy:'.$result->get('policy_id'));

        return back()->with('success', 'flash.attendance.policySaved');
    }
}
