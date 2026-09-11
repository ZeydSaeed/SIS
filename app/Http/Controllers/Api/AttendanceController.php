<?php

namespace App\Http\Controllers\Api;

use App\Application\Attendance\Commands\CancelAttendanceSessionCommand;
use App\Application\Attendance\Commands\CancelAttendanceSessionHandler;
use App\Application\Attendance\Commands\CloseAttendanceSessionCommand;
use App\Application\Attendance\Commands\CloseAttendanceSessionHandler;
use App\Application\Attendance\Commands\CorrectAttendanceRecordCommand;
use App\Application\Attendance\Commands\CorrectAttendanceRecordHandler;
use App\Application\Attendance\Commands\CreateAttendanceSessionCommand;
use App\Application\Attendance\Commands\CreateAttendanceSessionHandler;
use App\Application\Attendance\Commands\MarkSectionAttendanceCommand;
use App\Application\Attendance\Commands\MarkSectionAttendanceHandler;
use App\Application\Attendance\Queries\GetAttendanceSessionHandler;
use App\Application\Attendance\Queries\GetAttendanceSessionQuery;
use App\Application\Attendance\Queries\GetDailySectionSummaryHandler;
use App\Application\Attendance\Queries\GetDailySectionSummaryQuery;
use App\Application\Attendance\Queries\GetSectionAttendanceHandler;
use App\Application\Attendance\Queries\GetSectionAttendanceQuery;
use App\Application\Attendance\Queries\GetStudentAttendanceHandler;
use App\Application\Attendance\Queries\GetStudentAttendanceQuery;
use App\Application\Attendance\Queries\ListAttendanceSessionsHandler;
use App\Application\Attendance\Queries\ListAttendanceSessionsQuery;
use App\Domain\Attendance\Exceptions\SessionNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\CancelAttendanceSessionRequest;
use App\Http\Requests\Attendance\CloseAttendanceSessionRequest;
use App\Http\Requests\Attendance\CorrectAttendanceRecordRequest;
use App\Http\Requests\Attendance\CreateAttendanceSessionRequest;
use App\Http\Requests\Attendance\MarkSectionAttendanceRequest;
use App\Infrastructure\Persistence\Eloquent\AttendanceSessionRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function storeSession(
        CreateAttendanceSessionRequest $request,
        CreateAttendanceSessionHandler $handler,
    ): JsonResponse {
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
            'attendance.sessions.store',
            'created',
            $request->user(),
            'attendance_session:'.($result->sessionId ?? 'unknown'),
            ['academic_year_id' => (int) $request->validated('academic_year_id')],
        );

        return response()->json([
            'data' => ['id' => $result->sessionId],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function mark(
        MarkSectionAttendanceRequest $request,
        int $session,
        MarkSectionAttendanceHandler $handler,
    ): JsonResponse {
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

        $result = $handler->handle(new MarkSectionAttendanceCommand(
            sessionId: $session,
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            records: $records,
            recordedBy: $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        return response()->json([
            'data' => [
                'session_id' => $result->sessionId,
                'marked_count' => $result->markedCount,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function correct(
        CorrectAttendanceRecordRequest $request,
        int $session,
        int $student,
        CorrectAttendanceRecordHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(new CorrectAttendanceRecordCommand(
            sessionId: $session,
            studentId: $student,
            academicYearId: (int) $request->validated('academic_year_id'),
            schoolId: $schoolId,
            newStatus: (int) $request->validated('new_status'),
            newNotes: $request->validated('notes'),
            reason: (string) $request->validated('reason'),
            recordedBy: $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        return response()->json([
            'data' => [
                'record_id' => $result->recordId,
                'previous_status' => $result->previousStatus,
                'new_status' => $result->newStatus,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function close(
        CloseAttendanceSessionRequest $request,
        int $session,
        CloseAttendanceSessionHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = $request->header('X-Idempotency-Key');

        $result = $handler->handle(new CloseAttendanceSessionCommand(
            sessionId: $session,
            schoolId: $schoolId,
            closedBy: $request->user()?->id,
            idempotencyKey: is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
        ));

        return response()->json([
            'data' => ['session_id' => $result->sessionId],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function cancel(
        CancelAttendanceSessionRequest $request,
        int $session,
        CancelAttendanceSessionHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $idempotencyKey = trim((string) $request->header('X-Idempotency-Key'));

        $result = $handler->handle(new CancelAttendanceSessionCommand(
            sessionId: $session,
            schoolId: $schoolId,
            reason: (string) $request->validated('reason'),
            cancelledBy: $request->user()?->id,
            idempotencyKey: $idempotencyKey,
        ));

        $this->securityAudit->record(
            SecurityEventType::AttendanceDataModified,
            'attendance.sessions.cancel',
            'cancelled',
            $request->user(),
            'attendance_session:'.$session,
            [
                'previous_status' => $result->previousStatus,
                'new_status' => $result->newStatus,
            ],
        );

        return response()->json([
            'data' => [
                'session_id' => $result->sessionId,
                'previous_status' => $result->previousStatus,
                'new_status' => $result->newStatus,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function showSession(
        Request $request,
        int $session,
        GetAttendanceSessionHandler $handler,
    ): JsonResponse {
        $this->authorizeSessionView($request, $session);

        $schoolId = $this->schoolContext->requireId();
        $includeRecords = filter_var($request->query('include_records', false), FILTER_VALIDATE_BOOLEAN);

        $dto = $handler->handle(new GetAttendanceSessionQuery($schoolId, $session, $includeRecords));

        return response()->json(['data' => $dto->toArray()]);
    }

    public function indexSessions(Request $request, ListAttendanceSessionsHandler $handler): JsonResponse
    {
        $this->authorize('viewAny', AttendanceSessionRecord::class);

        $academicYearId = (int) $request->query('academic_year_id');
        if ($academicYearId < 1) {
            return response()->json([
                'message' => 'academic_year_id query parameter is required.',
                'error_code' => 'attendance.validation',
            ], 422);
        }

        $schoolId = $this->schoolContext->requireId();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);

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

        return response()->json($result->toArray());
    }

    public function sectionAttendance(
        Request $request,
        int $section,
        GetSectionAttendanceHandler $handler,
    ): JsonResponse {
        $this->authorize('viewAny', AttendanceSessionRecord::class);

        $academicYearId = (int) $request->query('academic_year_id');
        $date = (string) $request->query('date', '');
        if ($academicYearId < 1 || $date === '') {
            return response()->json([
                'message' => 'academic_year_id and date query parameters are required.',
                'error_code' => 'attendance.validation',
            ], 422);
        }

        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetSectionAttendanceQuery($schoolId, $section, $date, $academicYearId));

        return response()->json(['data' => $dto->toArray()]);
    }

    public function studentAttendance(
        Request $request,
        int $student,
        GetStudentAttendanceHandler $handler,
    ): JsonResponse {
        $this->authorize('viewAny', AttendanceSessionRecord::class);

        $academicYearId = (int) $request->query('academic_year_id');
        if ($academicYearId < 1) {
            return response()->json([
                'message' => 'academic_year_id query parameter is required.',
                'error_code' => 'attendance.validation',
            ], 422);
        }

        $schoolId = $this->schoolContext->requireId();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);

        $result = $handler->handle(new GetStudentAttendanceQuery(
            schoolId: $schoolId,
            studentId: $student,
            academicYearId: $academicYearId,
            dateFrom: $request->query('date_from'),
            dateTo: $request->query('date_to'),
            page: $page,
            perPage: $perPage,
        ));

        return response()->json($result->toArray());
    }

    public function dailySummary(
        Request $request,
        int $section,
        GetDailySectionSummaryHandler $handler,
    ): JsonResponse {
        $this->authorize('viewAny', AttendanceSessionRecord::class);

        $date = $request->query('date');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        if (($date === null || $date === '') && ($dateFrom === null || $dateFrom === '')) {
            return response()->json([
                'message' => 'date or date_from query parameter is required.',
                'error_code' => 'attendance.validation',
            ], 422);
        }

        $schoolId = $this->schoolContext->requireId();
        $rows = $handler->handle(new GetDailySectionSummaryQuery(
            schoolId: $schoolId,
            sectionId: $section,
            date: is_string($date) && $date !== '' ? $date : null,
            dateFrom: is_string($dateFrom) && $dateFrom !== '' ? $dateFrom : null,
            dateTo: is_string($dateTo) && $dateTo !== '' ? $dateTo : null,
        ));

        return response()->json([
            'data' => array_map(fn ($row) => $row->toArray(), $rows),
        ]);
    }

    private function authorizeSessionView(Request $request, int $sessionId): void
    {
        $record = AttendanceSessionRecord::query()->find($sessionId);
        if ($record === null) {
            throw SessionNotFoundException::forId($sessionId);
        }

        try {
            $this->authorize('view', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'attendance.sessions.show',
                'denied',
                $request->user(),
                "attendance_session:{$sessionId}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }
    }
}
