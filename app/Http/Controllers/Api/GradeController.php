<?php

namespace App\Http\Controllers\Api;

use App\Application\Exams\Commands\CorrectStudentGradeCommand;
use App\Application\Exams\Commands\CorrectStudentGradeHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Application\Exams\Commands\VoidStudentGradeCommand;
use App\Application\Exams\Commands\VoidStudentGradeHandler;
use App\Application\Exams\Queries\GetCurrentGradeForExamEnrollmentHandler;
use App\Application\Exams\Queries\GetCurrentGradeForExamEnrollmentQuery;
use App\Application\Exams\Queries\GetStudentGradeHandler;
use App\Application\Exams\Queries\GetStudentGradeQuery;
use App\Application\Exams\Queries\ListGradesForEnrollmentHandler;
use App\Application\Exams\Queries\ListGradesForEnrollmentQuery;
use App\Application\Exams\Queries\ListGradesForExamSessionHandler;
use App\Application\Exams\Queries\ListGradesForExamSessionQuery;
use App\Domain\Exams\Exceptions\GradeNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CorrectStudentGradeRequest;
use App\Http\Requests\Exams\EnterStudentGradeRequest;
use App\Http\Requests\Exams\FinalizeStudentGradeRequest;
use App\Http\Requests\Exams\VoidStudentGradeRequest;
use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function store(EnterStudentGradeRequest $request, EnterStudentGradeHandler $handler): JsonResponse
    {
        $schoolId = $this->schoolContext->requireId();

        $score = $request->validated('score');
        $result = $handler->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: (int) $request->validated('exam_enrollment_id'),
            score: $score !== null ? (string) $score : null,
            isAbsent: (bool) $request->validated('is_absent'),
            enteredBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'grades.store',
            'created',
            $request->user(),
            "grade:{$result->gradeId}",
            [
                'academic_year_id' => $result->academicYearId,
                'exam_enrollment_id' => (int) $request->validated('exam_enrollment_id'),
            ],
        );

        return response()->json([
            'data' => [
                'id' => $result->gradeId,
                'academic_year_id' => $result->academicYearId,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function show(Request $request, int $grade, GetStudentGradeHandler $handler): JsonResponse
    {
        $academicYearId = (int) $request->query('academic_year_id');
        if ($academicYearId < 1) {
            return response()->json([
                'message' => 'academic_year_id query parameter is required.',
                'error_code' => 'grades.validation',
            ], 422);
        }

        $record = StudentGradeRecord::query()
            ->where('id', $grade)
            ->where('academic_year_id', $academicYearId)
            ->first();

        if ($record === null) {
            throw GradeNotFoundException::forIdentity($grade, $academicYearId);
        }

        try {
            $this->authorize('view', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'grades.show',
                'denied',
                $request->user(),
                "grade:{$grade}",
            );
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetStudentGradeQuery($grade, $academicYearId, $schoolId));

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'grades.show',
            'allowed',
            $request->user(),
            "grade:{$grade}",
        );

        return response()->json(['data' => $dto->toArray()]);
    }

    public function correct(
        CorrectStudentGradeRequest $request,
        int $grade,
        CorrectStudentGradeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $score = $request->validated('score');
        $result = $handler->handle(new CorrectStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: $grade,
            academicYearId: (int) $request->validated('academic_year_id'),
            score: $score !== null ? (string) $score : null,
            isAbsent: (bool) $request->validated('is_absent'),
            reason: (string) $request->validated('reason'),
            correctedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'grades.correct',
            'corrected',
            $request->user(),
            "grade:{$result->newGradeId}",
            [
                'previous_grade_id' => $result->previousGradeId,
                'reason' => (string) $request->validated('reason'),
            ],
        );

        return response()->json([
            'data' => [
                'previous_grade_id' => $result->previousGradeId,
                'id' => $result->newGradeId,
                'academic_year_id' => $result->academicYearId,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function void(
        VoidStudentGradeRequest $request,
        int $grade,
        VoidStudentGradeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new VoidStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: $grade,
            academicYearId: (int) $request->validated('academic_year_id'),
            reason: (string) $request->validated('reason'),
            voidedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'grades.void',
            'voided',
            $request->user(),
            "grade:{$result->gradeId}",
            ['reason' => (string) $request->validated('reason')],
        );

        return response()->json([
            'data' => [
                'id' => $result->gradeId,
                'academic_year_id' => $result->academicYearId,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function finalize(
        FinalizeStudentGradeRequest $request,
        int $grade,
        FinalizeStudentGradeHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new FinalizeStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: $grade,
            academicYearId: (int) $request->validated('academic_year_id'),
            finalizedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'grades.finalize',
            'finalized',
            $request->user(),
            "grade:{$result->gradeId}",
        );

        return response()->json([
            'data' => [
                'id' => $result->gradeId,
                'academic_year_id' => $result->academicYearId,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function forExamSession(
        Request $request,
        int $examSession,
        ListGradesForExamSessionHandler $handler,
    ): JsonResponse {
        $this->authorize('viewAny', StudentGradeRecord::class);
        $academicYearId = (int) $request->query('academic_year_id');
        if ($academicYearId < 1) {
            return response()->json([
                'message' => 'academic_year_id query parameter is required.',
                'error_code' => 'grades.validation',
            ], 422);
        }

        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListGradesForExamSessionQuery($examSession, $academicYearId, $schoolId));

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'grades.for_exam_session',
            'allowed',
            $request->user(),
            "exam_session:{$examSession}",
        );

        return response()->json([
            'data' => array_map(fn ($dto) => $dto->toArray(), $items),
        ]);
    }

    public function forEnrollment(
        Request $request,
        int $enrollment,
        ListGradesForEnrollmentHandler $handler,
    ): JsonResponse {
        $this->authorize('viewAny', StudentGradeRecord::class);
        $academicYearId = (int) $request->query('academic_year_id');
        if ($academicYearId < 1) {
            return response()->json([
                'message' => 'academic_year_id query parameter is required.',
                'error_code' => 'grades.validation',
            ], 422);
        }

        $schoolId = $this->schoolContext->requireId();
        $items = $handler->handle(new ListGradesForEnrollmentQuery($enrollment, $academicYearId, $schoolId));

        return response()->json([
            'data' => array_map(fn ($dto) => $dto->toArray(), $items),
        ]);
    }

    public function currentForExamEnrollment(
        Request $request,
        int $examEnrollment,
        GetCurrentGradeForExamEnrollmentHandler $handler,
    ): JsonResponse {
        $this->authorize('viewAny', StudentGradeRecord::class);
        $academicYearId = (int) $request->query('academic_year_id');
        if ($academicYearId < 1) {
            return response()->json([
                'message' => 'academic_year_id query parameter is required.',
                'error_code' => 'grades.validation',
            ], 422);
        }

        $schoolId = $this->schoolContext->requireId();
        $dto = $handler->handle(new GetCurrentGradeForExamEnrollmentQuery(
            $examEnrollment,
            $academicYearId,
            $schoolId,
        ));

        return response()->json(['data' => $dto->toArray()]);
    }
}
