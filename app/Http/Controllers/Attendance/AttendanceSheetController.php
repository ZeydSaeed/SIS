<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Commands\CorrectAttendanceRecordCommand;
use App\Application\Attendance\Commands\CorrectAttendanceRecordHandler;
use App\Application\Attendance\Commands\OpenExceptionalAttendanceCommand;
use App\Application\Attendance\Commands\OpenExceptionalAttendanceHandler;
use App\Application\Attendance\Commands\OpenLessonAttendanceCommand;
use App\Application\Attendance\Commands\OpenLessonAttendanceHandler;
use App\Application\Attendance\Commands\ReviewAttendanceSheetCommand;
use App\Application\Attendance\Commands\ReviewAttendanceSheetHandler;
use App\Application\Attendance\Commands\SaveAttendanceSheetCommand;
use App\Application\Attendance\Commands\SaveAttendanceSheetHandler;
use App\Application\Attendance\Commands\SubmitAttendanceSheetCommand;
use App\Application\Attendance\Commands\SubmitAttendanceSheetHandler;
use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Application\Attendance\Queries\GetAttendanceSheetHandler;
use App\Application\Attendance\Queries\GetAttendanceSheetQuery;
use App\Application\Attendance\Queries\GetTeacherDayHandler;
use App\Application\Attendance\Queries\GetTeacherDayQuery;
use App\Domain\Attendance\Data\SheetMark;
use App\Domain\Attendance\ValueObjects\SheetAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CorrectAttendanceRecordRequest;
use App\Http\Requests\Attendance\OpenExceptionalAttendanceRequest;
use App\Http\Requests\Attendance\OpenLessonAttendanceRequest;
use App\Http\Requests\Attendance\ReviewAttendanceSheetRequest;
use App\Http\Requests\Attendance\SaveAttendanceSheetRequest;
use App\Http\Support\AttendanceRequestContext;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** «حضوري»: the teacher's day → open lesson → mark → draft / submit; and the sheet's review actions. */
final class AttendanceSheetController extends Controller
{
    public function __construct(
        private readonly AttendanceRequestContext $context,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function myDay(Request $request, GetTeacherDayHandler $handler, AttendanceWorkspaceReadInterface $read): Response
    {
        $this->authorize('viewAny', AttendanceSessionRecord::class);
        $yearId = $this->context->academicYearId($request);
        $actor = $this->context->actor($request, $yearId);
        $teacherId = $actor->isReviewer && $request->filled('teacher_id') ? (int) $request->query('teacher_id') : null;

        $day = $handler->handle(new GetTeacherDayQuery($this->context->schoolId(), $yearId, $request->query('date'), $actor, $teacherId));

        return Inertia::render('attendance/my-day', [
            'day' => $day->toArray(),
            'filters' => ['academic_year_id' => $yearId, 'teacher_id' => $teacherId],
            'teachers' => $actor->isReviewer ? $read->lookups($this->context->schoolId(), $yearId)['teachers'] : [],
            'lookups' => $actor->isReviewer ? $read->lookups($this->context->schoolId(), $yearId) : null,
            'abilities' => $this->context->abilities($request),
            'linked' => $actor->teacherId !== null,
        ]);
    }

    public function openLesson(OpenLessonAttendanceRequest $request, OpenLessonAttendanceHandler $handler): RedirectResponse
    {
        $yearId = $this->context->academicYearId($request);
        $result = $handler->handle(new OpenLessonAttendanceCommand(
            $this->context->schoolId(), $yearId, (int) $request->validated('schedule_id'), (string) $request->validated('date'),
            $this->context->actor($request, $yearId), $request->idempotencyKey('open'),
        ));
        $this->audit($request, 'attendance.web.lesson.open', 'opened', (int) $result->get('session_id'));

        return redirect()->route('attendance.sheets.show', ['session' => $result->get('session_id')]);
    }

    /** Exceptional (off-timetable) lesson — reviewers only, with a reason kept in the sheet history. */
    public function openExceptional(OpenExceptionalAttendanceRequest $request, OpenExceptionalAttendanceHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new OpenExceptionalAttendanceCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), (string) $request->validated('date'),
            (int) $request->validated('section_id'), (int) $request->validated('subject_id'), (int) $request->validated('teacher_id'),
            (int) $request->validated('period_id'), (string) $request->validated('reason'), (int) $request->user()?->id, $request->idempotencyKey('exceptional'),
        ));
        $this->audit($request, 'attendance.web.lesson.exceptional', 'opened', (int) $result->get('session_id'));

        return redirect()->route('attendance.sheets.show', ['session' => $result->get('session_id')]);
    }

    public function show(Request $request, int $session, GetAttendanceSheetHandler $handler): Response
    {
        $this->authorize('viewAny', AttendanceSessionRecord::class);
        $yearId = $this->context->academicYearId($request);
        $abilities = $this->context->abilities($request);
        $sheet = $handler->handle(new GetAttendanceSheetQuery(
            $this->context->schoolId(), $session, $this->context->actor($request, $yearId),
            $abilities['review_sheet'] || $abilities['view_reports'] || $abilities['correct'],
            $abilities['correct'],
        ));
        $this->securityAudit->record(SecurityEventType::AttendanceDataAccess, 'attendance.web.sheet.show', 'allowed', $request->user(), "attendance_session:{$session}");

        return Inertia::render('attendance/sheet', ['sheet' => $sheet->toArray(), 'abilities' => $abilities]);
    }

    public function saveDraft(SaveAttendanceSheetRequest $request, int $session, SaveAttendanceSheetHandler $handler): RedirectResponse|JsonResponse
    {
        $yearId = $this->context->academicYearId($request);
        $result = $handler->handle(new SaveAttendanceSheetCommand(
            $this->context->schoolId(), $yearId, $session, (int) $request->validated('version'), $this->marks($request),
            $this->context->actor($request, $yearId), $request->idempotencyKey("draft:{$session}"),
        ));
        $this->audit($request, 'attendance.web.sheet.draft', 'saved', $session);

        // Offline queue sync (ADR-023): JSON in, JSON out.
        if ($request->expectsJson()) {
            return response()->json(['data' => $result->data + ['from_idempotency' => $result->fromIdempotencyCache]]);
        }

        // No modal for a draft save: the sheet's status bar shows the confirmed version and time.
        return back()->with('attendanceResult', ['action' => 'draft', 'saved_at' => now()->toIso8601String()] + $result->data);
    }

    public function submit(SaveAttendanceSheetRequest $request, int $session, SubmitAttendanceSheetHandler $handler): RedirectResponse
    {
        if (! $request->boolean('confirm')) {
            throw ValidationException::withMessages(['confirm' => 'attendance.submit_confirm_required']);
        }
        $yearId = $this->context->academicYearId($request);
        $result = $handler->handle(new SubmitAttendanceSheetCommand(
            $this->context->schoolId(), $yearId, $session, (int) $request->validated('version'), $this->marks($request),
            $this->context->actor($request, $yearId), $request->idempotencyKey("submit:{$session}"),
        ));
        $this->audit($request, 'attendance.web.sheet.submit', 'submitted', $session);

        return back()->with('success', 'flash.attendance.sheetSubmitted')->with('attendanceResult', ['action' => 'submit'] + $result->data);
    }

    public function review(ReviewAttendanceSheetRequest $request, int $session, ReviewAttendanceSheetHandler $handler): RedirectResponse
    {
        $action = SheetAction::from((string) $request->validated('action'));
        $result = $handler->handle(new ReviewAttendanceSheetCommand(
            $this->context->schoolId(), $this->context->academicYearId($request), $session, (int) $request->validated('version'), $action,
            $request->validated('reason'), (int) $request->user()?->id, $request->idempotencyKey("review:{$session}:{$action->value}"),
        ));
        $this->audit($request, 'attendance.web.sheet.review', $action->value, $session);

        return back()->with('success', 'flash.attendance.review.'.$action->value)->with('attendanceResult', ['action' => $action->value] + $result->data);
    }

    public function correct(CorrectAttendanceRecordRequest $request, int $session, int $student, CorrectAttendanceRecordHandler $handler): RedirectResponse
    {
        $handler->handle(new CorrectAttendanceRecordCommand(
            sessionId: $session,
            studentId: $student,
            schoolId: $this->context->schoolId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            newStatus: (int) $request->validated('new_status'),
            newNotes: $request->validated('notes'),
            reason: (string) $request->validated('reason'),
            recordedBy: $request->user()?->id,
            idempotencyKey: 'u'.$request->user()?->id.':correct:'.trim((string) $request->header('X-Idempotency-Key')),
        ));
        $this->audit($request, 'attendance.web.record.correct', 'corrected', $session);

        return back()->with('success', 'flash.attendance.recordCorrected');
    }

    /**
     * @return list<SheetMark>
     */
    private function marks(SaveAttendanceSheetRequest $request): array
    {
        return array_map(static fn (array $row): SheetMark => SheetMark::fromArray($row), array_values((array) $request->validated('marks', [])));
    }

    private function audit(Request $request, string $action, string $result, int $sessionId): void
    {
        $this->securityAudit->record(SecurityEventType::AttendanceDataModified, $action, $result, $request->user(), "attendance_session:{$sessionId}");
    }
}
