<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Commands\DecideAttendanceExcuseCommand;
use App\Application\Attendance\Commands\DecideAttendanceExcuseHandler;
use App\Application\Attendance\Commands\SubmitAttendanceExcuseCommand;
use App\Application\Attendance\Commands\SubmitAttendanceExcuseHandler;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Queries\ListAttendanceExcusesHandler;
use App\Application\Attendance\Queries\ListAttendanceExcusesQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\DecideAttendanceExcuseRequest;
use App\Http\Requests\Attendance\SubmitAttendanceExcuseRequest;
use App\Http\Support\AttendanceFilterInput;
use App\Http\Support\AttendanceRequestContext;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «الأعذار»: record an excuse (pending) → approve (absences become excused) or reject (reason). */
final class AttendanceExcuseController extends Controller
{
    public function __construct(
        private readonly AttendanceRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function index(Request $request, ListAttendanceExcusesHandler $handler, AttendanceWorkspaceReadInterface $read): Response
    {
        $this->authorize('viewExcuses', AttendanceSessionRecord::class);
        $schoolId = $this->context->schoolId();
        $yearId = $this->context->academicYearId($request);
        $filters = AttendanceFilterInput::from($request, ['excuse_type', 'class_id', 'section_id'])
            + ['status' => AttendanceFilterInput::statuses($request, 'status', [1]), 'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100)];
        $page = max(1, (int) $request->query('page', 1));
        $list = $handler->handle(new ListAttendanceExcusesQuery(
            $schoolId, $yearId, $filters, $page, (int) config('attendance.per_page.excuses', 25),
            $request->query('student') !== null ? mb_substr((string) $request->query('student'), 0, 100) : null,
        ));

        return Inertia::render('attendance/excuses', [
            'excuses' => $list->toArray(),
            'filters' => $filters + ['page' => $page, 'academic_year_id' => $yearId, 'student' => (string) $request->query('student', '')],
            'lookups' => $read->lookups($schoolId, $yearId),
            'abilities' => $this->context->abilities($request),
        ]);
    }

    public function store(SubmitAttendanceExcuseRequest $request, SubmitAttendanceExcuseHandler $handler): RedirectResponse
    {
        $file = $request->file('document');
        $result = $handler->handle(new SubmitAttendanceExcuseCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), (int) $request->validated('enrollment_id'),
            (string) $request->validated('date_from'), (string) $request->validated('date_to'),
            $request->validated('session_id') !== null ? (int) $request->validated('session_id') : null,
            (int) $request->validated('excuse_type'), (string) $request->validated('reason'),
            $request->validated('document_file_id') !== null ? (int) $request->validated('document_file_id') : null,
            (int) $request->user()?->id, $request->idempotencyKey('excuse'),
            $file === null ? null : [
                'name' => (string) $file->getClientOriginalName(),
                'mime' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                'contents' => (string) file_get_contents($file->getRealPath()),
            ],
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.excuse.store', 'created', $request->user(), 'attendance_excuse:'.$result->get('excuse_id'), ['with_document' => $file !== null]);

        return back()->with('success', 'flash.attendance.excuseSubmitted');
    }

    public function decide(DecideAttendanceExcuseRequest $request, int $excuse, DecideAttendanceExcuseHandler $handler): RedirectResponse
    {
        $approve = $request->validated('decision') === 'approve';
        $result = $handler->handle(new DecideAttendanceExcuseCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), $excuse, (int) $request->validated('version'),
            $approve, $request->validated('note'), (int) $request->user()?->id, $request->idempotencyKey("excuse:{$excuse}"),
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, 'attendance.web.excuse.decide', $approve ? 'approved' : 'rejected', $request->user(), "attendance_excuse:{$excuse}", [
            'applied_records' => $result->get('applied_records'),
        ]);

        return back()->with('success', $approve ? 'flash.attendance.excuseApproved?count='.(int) $result->get('applied_records') : 'flash.attendance.excuseRejected');
    }
}
