<?php

namespace App\Http\Controllers\Attendance;

use App\Application\Attendance\Commands\CloseAttendanceSessionCommand;
use App\Application\Attendance\Commands\CloseAttendanceSessionHandler;
use App\Application\Attendance\Commands\CreateAttendanceSessionCommand;
use App\Application\Attendance\Commands\CreateAttendanceSessionHandler;
use App\Application\Attendance\Commands\MarkSectionAttendanceCommand;
use App\Application\Attendance\Commands\MarkSectionAttendanceHandler;
use App\Application\Attendance\Queries\GetAttendanceSessionHandler;
use App\Application\Attendance\Queries\GetAttendanceSessionQuery;
use App\Application\Attendance\Queries\ListAttendanceSessionsHandler;
use App\Application\Attendance\Queries\ListAttendanceSessionsQuery;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CloseAttendanceSessionRequest;
use App\Http\Requests\Attendance\CreateAttendanceSessionRequest;
use App\Http\Requests\Attendance\MarkSectionAttendanceWebRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class AttendancePageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListAttendanceSessionsHandler $handler): Response
    {
        $this->authorize('viewAny', AttendanceSessionRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);

        $sessions = [
            'data' => [],
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1],
        ];

        if ($academicYearId !== null) {
            $result = $handler->handle(new ListAttendanceSessionsQuery(
                schoolId: $schoolId,
                academicYearId: $academicYearId,
                sectionId: $request->filled('section_id') ? (int) $request->query('section_id') : null,
                dateFrom: $request->query('date_from'),
                dateTo: $request->query('date_to'),
                status: $request->filled('status') ? (int) $request->query('status') : null,
                page: $page,
                perPage: $perPage,
            ));
            $sessions = $result->toArray();
        }

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataAccess,
            'attendance.web.index',
            'allowed',
            $user,
            'attendance_sessions',
            ['page' => $page, 'academic_year_id' => $academicYearId],
        );

        return Inertia::render('attendance/index', [
            'sessions' => $sessions,
            'filters' => [
                'academic_year_id' => $academicYearId,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function show(Request $request, int $session, GetAttendanceSessionHandler $handler): Response
    {
        $record = AttendanceSessionRecord::query()->find($session);
        if ($record === null) {
            throw SessionNotFoundException::forId($session);
        }

        try {
            $this->authorize('view', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'attendance.web.show',
                'denied',
                $request->user(),
                "attendance_session:{$session}",
            );
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $detail = $handler->handle(new GetAttendanceSessionQuery($schoolId, $session, true));

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataAccess,
            'attendance.web.show',
            'allowed',
            $request->user(),
            "attendance_session:{$session}",
        );

        return Inertia::render('attendance/show', [
            'session' => $detail->toArray(),
        ]);
    }

    public function markForm(Request $request, int $session, GetAttendanceSessionHandler $handler): Response
    {
        $record = AttendanceSessionRecord::query()->find($session);
        if ($record === null) {
            throw SessionNotFoundException::forId($session);
        }

        try {
            $this->authorize('mark', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'attendance.web.mark',
                'denied',
                $request->user(),
                "attendance_session:{$session}",
            );
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $detail = $handler->handle(new GetAttendanceSessionQuery($schoolId, $session, true));

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataAccess,
            'attendance.web.mark',
            'allowed',
            $request->user(),
            "attendance_session:{$session}",
        );

        return Inertia::render('attendance/mark', [
            'session' => $detail->toArray(),
        ]);
    }

    public function markStore(
        MarkSectionAttendanceWebRequest $request,
        int $session,
        MarkSectionAttendanceHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $records = [];
        foreach ($request->validated('records') as $row) {
            $entry = [
                'studentId' => (int) $row['student_id'],
                'enrollmentId' => (int) $row['enrollment_id'],
                'status' => (int) $row['status'],
            ];
            if (array_key_exists('notes', $row)) {
                $entry['notes'] = $row['notes'];
            }
            $records[] = $entry;
        }

        $idempotencyKey = $request->header('X-Idempotency-Key');
        if (! is_string($idempotencyKey) || trim($idempotencyKey) === '') {
            $idempotencyKey = (string) Str::uuid();
        }

        $result = $handler->handle(new MarkSectionAttendanceCommand(
            sessionId: $session,
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            records: $records,
            recordedBy: $request->user()?->id,
            idempotencyKey: trim($idempotencyKey),
        ));

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataModified,
            'attendance.web.mark.store',
            'marked',
            $request->user(),
            "attendance_session:{$session}",
            ['marked_count' => $result->markedCount],
        );

        return redirect()
            ->route('attendance.show', ['session' => $session])
            ->with('success', 'Section attendance saved.');
    }

    public function close(
        CloseAttendanceSessionRequest $request,
        int $session,
        CloseAttendanceSessionHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = $request->header('X-Idempotency-Key');

        $handler->handle(new CloseAttendanceSessionCommand(
            sessionId: $session,
            schoolId: $schoolId,
            closedBy: $request->user()?->id,
            idempotencyKey: is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataModified,
            'attendance.web.close',
            'closed',
            $request->user(),
            "attendance_session:{$session}",
        );

        return redirect()
            ->route('attendance.show', ['session' => $session])
            ->with('success', 'Attendance session closed.');
    }

    public function create(Request $request): Response
    {
        $this->authorize('createSession', AttendanceSessionRecord::class);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);

        return Inertia::render('attendance/create', [
            'defaults' => [
                'academic_year_id' => $academicYearId,
                'session_date' => now()->toDateString(),
            ],
        ]);
    }

    public function store(
        CreateAttendanceSessionRequest $request,
        CreateAttendanceSessionHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = $request->header('X-Idempotency-Key');

        $result = $handler->handle(new CreateAttendanceSessionCommand(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            sectionId: (int) $request->validated('section_id'),
            subjectId: (int) $request->validated('subject_id'),
            sessionDate: (string) $request->validated('session_date'),
            teacherId: (int) $request->validated('teacher_id'),
            periodId: $request->validated('period_id') !== null
                ? (int) $request->validated('period_id')
                : null,
            createdBy: $request->user()?->id,
            idempotencyKey: is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataModified,
            'attendance.web.store',
            'created',
            $request->user(),
            'attendance_session:'.($result->sessionId ?? 'unknown'),
            ['academic_year_id' => (int) $request->validated('academic_year_id')],
        );

        return redirect()
            ->route('attendance.show', ['session' => $result->sessionId])
            ->with('success', 'Attendance session created.');
    }
}
