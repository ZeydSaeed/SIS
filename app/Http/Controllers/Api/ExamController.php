<?php

namespace App\Http\Controllers\Api;

use App\Application\Exams\Commands\CancelExamCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentHandler;
use App\Application\Exams\Commands\CancelExamHandler;
use App\Application\Exams\Commands\CloseExamSessionCommand;
use App\Application\Exams\Commands\CloseExamSessionHandler;
use App\Application\Exams\Commands\CreateExamCommand;
use App\Application\Exams\Commands\CreateExamEnrollmentCommand;
use App\Application\Exams\Commands\CreateExamEnrollmentHandler;
use App\Application\Exams\Commands\CreateExamHandler;
use App\Application\Exams\Commands\CreateExamSessionCommand;
use App\Application\Exams\Commands\CreateExamSessionHandler;
use App\Application\Exams\Commands\OpenExamSessionCommand;
use App\Application\Exams\Commands\OpenExamSessionHandler;
use App\Application\Exams\Commands\PresentExamEnrollmentCommand;
use App\Application\Exams\Commands\PresentExamEnrollmentHandler;
use App\Application\Exams\Commands\ReopenExamEnrollmentCommand;
use App\Application\Exams\Commands\ReopenExamEnrollmentHandler;
use App\Application\Exams\Commands\UpdateExamCommand;
use App\Application\Exams\Commands\UpdateExamEnrollmentCommand;
use App\Application\Exams\Commands\UpdateExamEnrollmentHandler;
use App\Application\Exams\Commands\UpdateExamHandler;
use App\Application\Exams\Commands\UpdateExamSessionCommand;
use App\Application\Exams\Commands\UpdateExamSessionHandler;
use App\Application\Exams\DTOs\ExamDTO;
use App\Application\Exams\DTOs\ExamEnrollmentDTO;
use App\Application\Exams\DTOs\ExamSessionDTO;
use App\Application\Exams\Queries\GetExamEnrollmentHandler;
use App\Application\Exams\Queries\GetExamEnrollmentQuery;
use App\Application\Exams\Queries\GetExamHandler;
use App\Application\Exams\Queries\GetExamQuery;
use App\Application\Exams\Queries\GetExamSessionHandler;
use App\Application\Exams\Queries\GetExamSessionQuery;
use App\Application\Exams\Queries\ListExamEnrollmentsHandler;
use App\Application\Exams\Queries\ListExamEnrollmentsQuery;
use App\Application\Exams\Queries\ListExamSessionsHandler;
use App\Application\Exams\Queries\ListExamSessionsQuery;
use App\Application\Exams\Queries\ListExamsHandler;
use App\Application\Exams\Queries\ListExamsQuery;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CancelExamEnrollmentRequest;
use App\Http\Requests\Exams\CancelExamRequest;
use App\Http\Requests\Exams\CloseExamSessionRequest;
use App\Http\Requests\Exams\CreateExamEnrollmentRequest;
use App\Http\Requests\Exams\CreateExamRequest;
use App\Http\Requests\Exams\CreateExamSessionRequest;
use App\Http\Requests\Exams\ListExamEnrollmentsRequest;
use App\Http\Requests\Exams\ListExamSessionsRequest;
use App\Http\Requests\Exams\ListExamsRequest;
use App\Http\Requests\Exams\OpenExamSessionRequest;
use App\Http\Requests\Exams\PresentExamEnrollmentRequest;
use App\Http\Requests\Exams\ReopenExamEnrollmentRequest;
use App\Http\Requests\Exams\ShowExamEnrollmentRequest;
use App\Http\Requests\Exams\ShowExamRequest;
use App\Http\Requests\Exams\ShowExamSessionRequest;
use App\Http\Requests\Exams\UpdateExamEnrollmentRequest;
use App\Http\Requests\Exams\UpdateExamRequest;
use App\Http\Requests\Exams\UpdateExamSessionRequest;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\JsonResponse;

class ExamController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
        private readonly ExamRepositoryInterface $exams,
    ) {}

    public function index(ListExamsRequest $request, ListExamsHandler $handler): JsonResponse
    {
        $items = $handler->handle(new ListExamsQuery(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: $request->validated('academic_year_id') !== null
                ? (int) $request->validated('academic_year_id')
                : null,
            status: $request->validated('status') !== null
                ? (int) $request->validated('status')
                : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'exams.index',
            'viewed',
            $request->user(),
            'exams',
            [],
        );

        return response()->json([
            'data' => array_map(static fn (ExamDTO $dto): array => $dto->toArray(), $items),
            'meta' => [
                'count' => count($items),
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function show(ShowExamRequest $request, int $exam, GetExamHandler $handler): JsonResponse
    {
        $dto = $handler->handle(new GetExamQuery(
            schoolId: $this->schoolContext->requireId(),
            examId: $exam,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Exam not found.',
                'error_code' => 'exam.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'exams.show',
            'viewed',
            $request->user(),
            'exam:'.$exam,
            [],
        );

        return response()->json([
            'data' => $dto->toArray(),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function store(CreateExamRequest $request, CreateExamHandler $handler): JsonResponse
    {
        $result = $handler->handle(new CreateExamCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            termId: (int) $request->validated('term_id'),
            examTypeId: (int) $request->validated('exam_type_id'),
            name: (string) $request->validated('name'),
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.store',
            'created',
            $request->user(),
            'exam:'.$result->examId,
            ['academic_year_id' => $result->academicYearId],
        );

        return response()->json([
            'data' => [
                'id' => $result->examId,
                'academic_year_id' => $result->academicYearId,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function update(UpdateExamRequest $request, int $exam, UpdateExamHandler $handler): JsonResponse
    {
        $result = $handler->handle(new UpdateExamCommand(
            schoolId: $this->schoolContext->requireId(),
            examId: $exam,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            name: $request->validated('name'),
            startDate: $request->validated('start_date'),
            endDate: $request->validated('end_date'),
            examTypeId: $request->validated('exam_type_id') !== null
                ? (int) $request->validated('exam_type_id')
                : null,
            termId: $request->validated('term_id') !== null
                ? (int) $request->validated('term_id')
                : null,
            targetStatus: $request->validated('target_status') !== null
                ? (int) $request->validated('target_status')
                : null,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.update',
            'updated',
            $request->user(),
            'exam:'.$exam,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $result->examId,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function cancel(CancelExamRequest $request, int $exam, CancelExamHandler $handler): JsonResponse
    {
        $result = $handler->handle(new CancelExamCommand(
            schoolId: $this->schoolContext->requireId(),
            examId: $exam,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.cancel',
            'cancelled',
            $request->user(),
            'exam:'.$exam,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $result->examId,
                'status' => $result->status,
                'cancelled_session_ids' => $result->cancelledSessionIds,
                'withdrawn_enrollment_ids' => $result->withdrawnEnrollmentIds,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function indexSessions(
        ListExamSessionsRequest $request,
        int $exam,
        ListExamSessionsHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListExamSessionsQuery(
            schoolId: $this->schoolContext->requireId(),
            examId: $exam,
            status: $request->validated('status') !== null
                ? (int) $request->validated('status')
                : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'exams.sessions.index',
            'viewed',
            $request->user(),
            'exam:'.$exam,
            [],
        );

        return response()->json([
            'data' => array_map(static fn (ExamSessionDTO $dto): array => $dto->toArray(), $items),
            'meta' => [
                'count' => count($items),
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function showSession(
        ShowExamSessionRequest $request,
        int $examSession,
        GetExamSessionHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetExamSessionQuery(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $examSession,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Exam session not found.',
                'error_code' => 'exam.session_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'exams.sessions.show',
            'viewed',
            $request->user(),
            'exam_session:'.$examSession,
            [],
        );

        return response()->json([
            'data' => $dto->toArray(),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeSession(
        CreateExamSessionRequest $request,
        int $exam,
        CreateExamSessionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateExamSessionCommand(
            schoolId: $this->schoolContext->requireId(),
            examId: $exam,
            subjectId: (int) $request->validated('subject_id'),
            sessionDate: (string) $request->validated('session_date'),
            startTime: (string) $request->validated('start_time'),
            endTime: (string) $request->validated('end_time'),
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            roomId: $request->validated('room_id') !== null
                ? (int) $request->validated('room_id')
                : null,
            maxGrade: (int) ($request->validated('max_grade') ?? 100),
            passGrade: (int) ($request->validated('pass_grade') ?? 50),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.sessions.store',
            'created',
            $request->user(),
            'exam_session:'.$result->examSessionId,
            ['exam_id' => $result->examId],
        );

        return response()->json([
            'data' => [
                'id' => $result->examSessionId,
                'exam_id' => $result->examId,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function updateSession(
        UpdateExamSessionRequest $request,
        int $examSession,
        UpdateExamSessionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new UpdateExamSessionCommand(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $examSession,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            sessionDate: $request->validated('session_date'),
            startTime: $request->validated('start_time'),
            endTime: $request->validated('end_time'),
            roomId: $request->exists('room_id')
                ? ($request->validated('room_id') !== null ? (int) $request->validated('room_id') : null)
                : null,
            roomIdProvided: $request->exists('room_id'),
            maxGrade: $request->validated('max_grade') !== null
                ? (int) $request->validated('max_grade')
                : null,
            passGrade: $request->validated('pass_grade') !== null
                ? (int) $request->validated('pass_grade')
                : null,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.sessions.update',
            'updated',
            $request->user(),
            'exam_session:'.$examSession,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $result->examSessionId,
                'exam_id' => $result->examId,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function openSession(
        OpenExamSessionRequest $request,
        int $examSession,
        OpenExamSessionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new OpenExamSessionCommand(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $examSession,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.sessions.open',
            'opened',
            $request->user(),
            'exam_session:'.$examSession,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $result->examSessionId,
                'exam_id' => $result->examId,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function closeSession(
        CloseExamSessionRequest $request,
        int $examSession,
        CloseExamSessionHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CloseExamSessionCommand(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $examSession,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.sessions.close',
            'closed',
            $request->user(),
            'exam_session:'.$examSession,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $result->examSessionId,
                'exam_id' => $result->examId,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function indexEnrollments(
        ListExamEnrollmentsRequest $request,
        int $examSession,
        ListExamEnrollmentsHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListExamEnrollmentsQuery(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $examSession,
            status: $request->validated('status') !== null
                ? (int) $request->validated('status')
                : null,
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'exams.enrollments.index',
            'viewed',
            $request->user(),
            'exam_session:'.$examSession,
            [],
        );

        return response()->json([
            'data' => array_map(static fn (ExamEnrollmentDTO $dto): array => $dto->toArray(), $items),
            'meta' => [
                'count' => count($items),
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function showEnrollment(
        ShowExamEnrollmentRequest $request,
        int $examEnrollment,
        GetExamEnrollmentHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetExamEnrollmentQuery(
            schoolId: $this->schoolContext->requireId(),
            examEnrollmentId: $examEnrollment,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Exam enrollment not found.',
                'error_code' => 'exam.enrollment_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'exams.enrollments.show',
            'viewed',
            $request->user(),
            'exam_enrollment:'.$examEnrollment,
            [],
        );

        return response()->json([
            'data' => $dto->toArray(),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeEnrollment(
        CreateExamEnrollmentRequest $request,
        int $examSession,
        CreateExamEnrollmentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new CreateExamEnrollmentCommand(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $examSession,
            enrollmentId: (int) $request->validated('enrollment_id'),
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            seatNumber: $request->validated('seat_number'),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.enrollments.store',
            'created',
            $request->user(),
            'exam_enrollment:'.$result->examEnrollmentId,
            ['exam_session_id' => $result->examSessionId],
        );

        return response()->json([
            'data' => [
                'id' => $result->examEnrollmentId,
                'exam_session_id' => $result->examSessionId,
                'enrollment_id' => $result->enrollmentId,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function updateEnrollment(
        UpdateExamEnrollmentRequest $request,
        int $examEnrollment,
        UpdateExamEnrollmentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $snapshot = $this->exams->findExamEnrollmentByIdAndSchool($examEnrollment, $schoolId);
        if ($snapshot === null) {
            return response()->json([
                'message' => 'Exam enrollment not found.',
                'error_code' => 'exam.enrollment_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $result = $handler->handle(new UpdateExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $examEnrollment,
            examSessionId: $snapshot->examSessionId,
            enrollmentId: $snapshot->enrollmentId,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            status: $request->validated('status') !== null
                ? (int) $request->validated('status')
                : null,
            seatNumberProvided: $request->exists('seat_number'),
            seatNumber: $request->exists('seat_number')
                ? $request->validated('seat_number')
                : null,
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.enrollments.update',
            'updated',
            $request->user(),
            'exam_enrollment:'.$examEnrollment,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $result->examEnrollmentId,
                'exam_session_id' => $result->examSessionId,
                'enrollment_id' => $result->enrollmentId,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function cancelEnrollment(
        CancelExamEnrollmentRequest $request,
        int $examEnrollment,
        CancelExamEnrollmentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $snapshot = $this->exams->findExamEnrollmentByIdAndSchool($examEnrollment, $schoolId);
        if ($snapshot === null) {
            return response()->json([
                'message' => 'Exam enrollment not found.',
                'error_code' => 'exam.enrollment_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $result = $handler->handle(new CancelExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $examEnrollment,
            examSessionId: $snapshot->examSessionId,
            enrollmentId: $snapshot->enrollmentId,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.enrollments.cancel',
            'cancelled',
            $request->user(),
            'exam_enrollment:'.$examEnrollment,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $result->examEnrollmentId,
                'exam_session_id' => $result->examSessionId,
                'enrollment_id' => $result->enrollmentId,
                'status' => $result->status,
                'noop' => $result->noop,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function reopenEnrollment(
        ReopenExamEnrollmentRequest $request,
        int $examEnrollment,
        ReopenExamEnrollmentHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReopenExamEnrollmentCommand(
            schoolId: $this->schoolContext->requireId(),
            examEnrollmentId: $examEnrollment,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'exam_enrollment.reopen_failed';

            return response()->json([
                'message' => 'Exam enrollment reopen rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'exam_enrollment.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.enrollments.reopen',
            'reopened',
            $request->user(),
            'exam_enrollment:'.$examEnrollment,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'id' => $result->examEnrollmentId,
                'exam_session_id' => $result->examSessionId,
                'enrollment_id' => $result->enrollmentId,
                'status' => $result->status,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function presentEnrollment(
        PresentExamEnrollmentRequest $request,
        int $examEnrollment,
        PresentExamEnrollmentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $snapshot = $this->exams->findExamEnrollmentByIdAndSchool($examEnrollment, $schoolId);
        if ($snapshot === null) {
            return response()->json([
                'message' => 'Exam enrollment not found.',
                'error_code' => 'exam.enrollment_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $result = $handler->handle(new PresentExamEnrollmentCommand(
            schoolId: $schoolId,
            examEnrollmentId: $examEnrollment,
            examSessionId: $snapshot->examSessionId,
            enrollmentId: $snapshot->enrollmentId,
            actorUserId: (int) $request->user()->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            correlationId: CorrelationContext::id(),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'exams.enrollments.present',
            'presented',
            $request->user(),
            'exam_enrollment:'.$examEnrollment,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $result->examEnrollmentId,
                'exam_session_id' => $result->examSessionId,
                'enrollment_id' => $result->enrollmentId,
                'status' => $result->status,
                'noop' => $result->noop,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }
}
