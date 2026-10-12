<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Commands\IssueNfcStationTokenCommand;
use App\Application\Attendance\Commands\IssueNfcStationTokenHandler;
use App\Application\Attendance\Commands\IssueStaffCheckinCodeCommand;
use App\Application\Attendance\Commands\IssueStaffCheckinCodeHandler;
use App\Application\Attendance\Commands\RecordStaffAttendanceCommand;
use App\Application\Attendance\Commands\RecordStaffAttendanceHandler;
use App\Application\Attendance\Commands\StaffCheckInCommand;
use App\Application\Attendance\Commands\StaffCheckInHandler;
use App\Application\Attendance\Queries\GetAttendancePolicyHandler;
use App\Application\Attendance\Queries\GetAttendancePolicyQuery;
use App\Application\Attendance\Queries\GetStaffAttendanceDayHandler;
use App\Application\Attendance\Queries\GetStaffAttendanceDayQuery;
use App\Application\Teachers\Commands\LinkTeacherUserAccountCommand;
use App\Application\Teachers\Commands\LinkTeacherUserAccountHandler;
use App\Domain\Shared\Exceptions\SisDomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\IssueNfcStationTokenRequest;
use App\Http\Requests\Attendance\IssueStaffCheckinCodeRequest;
use App\Http\Requests\Attendance\LinkTeacherAccountRequest;
use App\Http\Requests\Attendance\RecordStaffAttendanceRequest;
use App\Http\Requests\Attendance\StaffCheckInRequest;
use App\Http\Support\AttendanceFilterInput;
use App\Http\Support\AttendanceRequestContext;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «دوام المعلمين»: work attendance, separate from lesson attendance. */
final class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, GetStaffAttendanceDayHandler $handler, GetAttendancePolicyHandler $policies): Response
    {
        $this->authorize('viewAny', AttendanceSessionRecord::class);
        $yearId = $this->context->academicYearId($request);
        $abilities = $this->context->abilities($request);
        $actor = $this->context->actor($request, $yearId);
        $day = $handler->handle(new GetStaffAttendanceDayQuery(
            $this->context->schoolId(), $yearId, AttendanceFilterInput::date($request, 'date'), $actor->teacherId, $abilities['record_staff'],
        ));
        $policy = $policies->handle(new GetAttendancePolicyQuery($this->context->schoolId(), $yearId))->toArray()['policy'];
        $code = $request->query('code');

        return Inertia::render('attendance/staff', [
            'day' => $day->toArray(),
            'filters' => ['academic_year_id' => $yearId],
            'linked' => $actor->teacherId !== null,
            'evidence' => ['qr_enabled' => (bool) $policy['qr_enabled'], 'nfc_enabled' => (bool) $policy['nfc_enabled']],
            // Scanned QR: the code arrives in the URL and pre-fills the check-in window (it still has to be submitted).
            'prefill' => is_string($code) && preg_match('/^\d{4,10}$/', $code) === 1 ? ['code' => $code, 'via' => $request->query('via') === 'qr' ? 'qr' : 'code'] : null,
            'abilities' => $abilities,
        ]);
    }

    public function store(RecordStaffAttendanceRequest $request, RecordStaffAttendanceHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new RecordStaffAttendanceCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), (int) $request->validated('teacher_id'),
            (string) $request->validated('work_date'), (int) $request->validated('attendance_status'),
            $request->validated('check_in'), $request->validated('check_out'),
            $request->validated('minutes_late') !== null ? (int) $request->validated('minutes_late') : null,
            $request->validated('notes'), $request->validated('reason'),
            $request->validated('version') !== null ? (int) $request->validated('version') : null,
            (int) $request->user()?->id, $request->idempotencyKey('staff:'.$request->validated('teacher_id').':'.$request->validated('work_date')),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.staff.record', $result->get('corrected') ? 'corrected' : 'recorded', $request->user(), 'attendance_staff_record:'.$result->get('record_id'));

        return back()->with('success', $result->get('corrected') ? 'flash.attendance.staffCorrected' : 'flash.attendance.staffRecorded');
    }

    public function checkIn(StaffCheckInRequest $request, StaffCheckInHandler $handler): RedirectResponse
    {
        $yearId = $this->context->academicYearId($request);
        $via = (string) ($request->validated('via') ?? 'code');
        try {
            $result = $handler->handle(new StaffCheckInCommand(
                $this->context->schoolId(), $yearId, $this->context->actor($request, $yearId)->teacherId, (string) $request->validated('code'),
                (int) $request->user()?->id, $request->idempotencyKey('checkin'), $via,
            ));
        } catch (SisDomainException $e) {
            // Rejected attempts are recorded (never the code itself) for abuse review.
            $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.staff.checkin', 'rejected', $request->user(), 'attendance_checkin', ['code' => $e->errorCode(), 'via' => $via]);

            throw $e;
        }
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.staff.checkin', (string) $result->get('action'), $request->user(), 'attendance_staff_record:'.$result->get('record_id'), ['via' => $via]);

        return redirect()->route('attendance.staff.index')
            ->with('success', $result->get('action') === 'check_out' ? 'flash.attendance.checkedOut' : 'flash.attendance.checkedIn')
            ->with('attendanceResult', ['action' => $result->get('action')] + $result->data);
    }

    public function issueCode(IssueStaffCheckinCodeRequest $request, IssueStaffCheckinCodeHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new IssueStaffCheckinCodeCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), (int) $request->user()?->id, $request->idempotencyKey('checkin-code'),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.staff.code', 'issued', $request->user(), 'attendance_checkin_code:'.$result->get('code_id'));

        return back()->with('attendanceResult', ['action' => 'code', 'code' => $result->get('code'), 'qr_svg' => $result->get('qr_svg'), 'valid_until' => $result->get('valid_until')]);
    }

    public function issueNfcToken(IssueNfcStationTokenRequest $request, IssueNfcStationTokenHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new IssueNfcStationTokenCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), (string) ($request->validated('label') ?? ''), (int) $request->user()?->id, $request->idempotencyKey('nfc-token'),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.staff.nfc_token', 'issued', $request->user(), 'attendance_checkin_code:'.$result->get('token_id'));

        return back()->with('attendanceResult', ['action' => 'nfc_token', 'token' => $result->get('token'), 'label' => $result->get('label'), 'valid_until' => $result->get('valid_until')]);
    }

    public function linkAccount(LinkTeacherAccountRequest $request, int $teacher, LinkTeacherUserAccountHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new LinkTeacherUserAccountCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), $teacher, $request->validated('email'), (int) $request->user()?->id, $request->idempotencyKey("teacher-account:{$teacher}"),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.teacher.account', $result->userId === null ? 'unlinked' : 'linked', $request->user(), "teacher:{$teacher}", ['user_id' => $result->userId]);

        return back()->with('success', $result->userId === null ? 'flash.attendance.accountUnlinked' : 'flash.attendance.accountLinked');
    }
}
