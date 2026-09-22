<?php

namespace App\Http\Controllers\Api;

use App\Application\Enrollment\Commands\AssignEnrollmentSubjectCommand;
use App\Application\Enrollment\Commands\AssignEnrollmentSubjectHandler;
use App\Application\Enrollment\Commands\CancelEnrollmentCommand;
use App\Application\Enrollment\Commands\CancelEnrollmentHandler;
use App\Application\Enrollment\Commands\DeactivateEnrollmentSubjectCommand;
use App\Application\Enrollment\Commands\DeactivateEnrollmentSubjectHandler;
use App\Application\Enrollment\Commands\ReactivateEnrollmentSubjectCommand;
use App\Application\Enrollment\Commands\ReactivateEnrollmentSubjectHandler;
use App\Application\Enrollment\Commands\ReopenEnrollmentCommand;
use App\Application\Enrollment\Commands\ReopenEnrollmentHandler;
use App\Application\Enrollment\Commands\EnrollStudentCommand;
use App\Application\Enrollment\Commands\EnrollStudentHandler;
use App\Application\Enrollment\Commands\UpdateEnrollmentPlacementCommand;
use App\Application\Enrollment\Commands\UpdateEnrollmentPlacementHandler;
use App\Application\Enrollment\DTOs\EnrollmentSubjectDTO;
use App\Application\Enrollment\Queries\GetEnrollmentHandler;
use App\Application\Enrollment\Queries\GetEnrollmentQuery;
use App\Application\Enrollment\Queries\GetEnrollmentSubjectHandler;
use App\Application\Enrollment\Queries\GetEnrollmentSubjectQuery;
use App\Application\Enrollment\Queries\ListEnrollmentSubjectsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentSubjectsQuery;
use App\Application\Enrollment\Queries\ListEnrollmentsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentsQuery;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\AssignEnrollmentSubjectRequest;
use App\Http\Requests\Enrollment\CancelEnrollmentRequest;
use App\Http\Requests\Enrollment\DeactivateEnrollmentSubjectRequest;
use App\Http\Requests\Enrollment\ReactivateEnrollmentSubjectRequest;
use App\Http\Requests\Enrollment\ReopenEnrollmentRequest;
use App\Http\Requests\Enrollment\EnrollStudentRequest;
use App\Http\Requests\Enrollment\ListEnrollmentSubjectsRequest;
use App\Http\Requests\Enrollment\ShowEnrollmentSubjectRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentPlacementRequest;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    public function __construct(
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function index(Request $request, ListEnrollmentsHandler $handler): JsonResponse
    {
        $this->authorize('viewAny', EnrollmentRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $academicYearId = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);
        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $q = trim((string) $request->query('q', ''));
        $gender = $request->filled('gender') ? (int) $request->query('gender') : null;
        $gender = $gender === 1 || $gender === 2 ? $gender : null;

        $result = $handler->handle(new ListEnrollmentsQuery(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            page: $page,
            perPage: $perPage,
            status: $status,
            q: $q,
            gender: $gender,
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollments.index',
            'allowed',
            $request->user(),
            'enrollments',
            ['page' => $page, 'per_page' => $perPage],
        );

        return response()->json($result->toArray());
    }

    public function show(Request $request, int $enrollment, GetEnrollmentHandler $handler): JsonResponse
    {
        $record = EnrollmentRecord::query()->find($enrollment);

        if ($record === null) {
            throw EnrollmentNotFoundException::forId($enrollment);
        }

        try {
            $this->authorize('view', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'enrollments.show',
                'denied',
                $request->user(),
                "enrollment:{$enrollment}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $detail = $handler->handle(new GetEnrollmentQuery($enrollment, $schoolId));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollments.show',
            'allowed',
            $request->user(),
            "enrollment:{$enrollment}",
        );

        return response()->json(['data' => $detail->toArray()]);
    }

    public function store(
        EnrollStudentRequest $request,
        EnrollStudentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(new EnrollStudentCommand(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            studentId: (int) $request->validated('student_id'),
            classId: (int) $request->validated('class_id'),
            sectionId: (int) $request->validated('section_id'),
            effectiveFrom: $request->validated('effective_from'),
            specializationId: $request->validated('specialization_id'),
            enrolledBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.store',
            'created',
            $request->user(),
            "enrollment:{$result->enrollmentId}",
        );

        return response()->json([
            'data' => [
                'id' => $result->enrollmentId,
                'enrollment_number' => $result->enrollmentNumber,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], 201);
    }

    public function update(
        UpdateEnrollmentPlacementRequest $request,
        int $enrollment,
        UpdateEnrollmentPlacementHandler $handler,
    ): JsonResponse {
        $record = EnrollmentRecord::query()->find($enrollment);

        if ($record === null) {
            throw EnrollmentNotFoundException::forId($enrollment);
        }

        try {
            $this->authorize('update', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'enrollments.update',
                'denied',
                $request->user(),
                "enrollment:{$enrollment}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(new UpdateEnrollmentPlacementCommand(
            enrollmentId: $enrollment,
            schoolId: $schoolId,
            classId: (int) $request->validated('class_id'),
            sectionId: (int) $request->validated('section_id'),
            specializationId: $request->validated('specialization_id'),
            updatedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.update',
            'updated',
            $request->user(),
            "enrollment:{$enrollment}",
            [
                'class_id' => $result->classId,
                'section_id' => $result->sectionId,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $result->enrollmentId,
                'class_id' => $result->classId,
                'section_id' => $result->sectionId,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function cancel(
        CancelEnrollmentRequest $request,
        int $enrollment,
        CancelEnrollmentHandler $handler,
    ): JsonResponse {
        $record = EnrollmentRecord::query()->find($enrollment);

        if ($record === null) {
            throw EnrollmentNotFoundException::forId($enrollment);
        }

        try {
            $this->authorize('cancel', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'enrollments.cancel',
                'denied',
                $request->user(),
                "enrollment:{$enrollment}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $effectiveTo = $request->validated('effective_to')
            ?? now()->format('Y-m-d');

        $result = $handler->handle(new CancelEnrollmentCommand(
            enrollmentId: $enrollment,
            schoolId: $schoolId,
            effectiveTo: $effectiveTo,
            cancelledBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.cancel',
            'cancelled',
            $request->user(),
            "enrollment:{$enrollment}",
            ['effective_to' => $result->effectiveTo],
        );

        return response()->json([
            'data' => [
                'id' => $result->enrollmentId,
                'effective_to' => $result->effectiveTo,
                'status' => $result->status,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }

    public function reopen(
        ReopenEnrollmentRequest $request,
        int $enrollment,
        ReopenEnrollmentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new ReopenEnrollmentCommand(
            schoolId: $schoolId,
            enrollmentId: $enrollment,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'enrollment.reopen_failed';

            return response()->json([
                'message' => 'Enrollment reopen rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'enrollment.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.reopen',
            'reopened',
            $request->user(),
            "enrollment:{$enrollment}",
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'id' => $result->enrollmentId,
                'status' => $result->status,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function storeSubject(
        AssignEnrollmentSubjectRequest $request,
        int $enrollment,
        AssignEnrollmentSubjectHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new AssignEnrollmentSubjectCommand(
            schoolId: $schoolId,
            enrollmentId: $enrollment,
            subjectId: (int) $request->validated('subject_id'),
            isElective: (bool) ($request->validated('is_elective') ?? false),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'enrollment.subject_assign_failed';

            return response()->json([
                'message' => 'Enrollment subject assign rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], in_array($code, [
                'enrollment.not_found',
                'curriculum.subject_not_found',
            ], true) ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.subjects.store',
            'assigned',
            $request->user(),
            'enrollment:'.$enrollment,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'link_id' => $result->linkId,
                'enrollment_id' => $enrollment,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    public function indexSubjects(
        ListEnrollmentSubjectsRequest $request,
        int $enrollment,
        ListEnrollmentSubjectsHandler $handler,
    ): JsonResponse {
        $items = $handler->handle(new ListEnrollmentSubjectsQuery(
            $this->schoolContext->requireId(),
            $enrollment,
        ));

        if ($items === null) {
            return response()->json([
                'message' => 'Enrollment not found.',
                'error_code' => 'enrollment.not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollments.subjects.index',
            'listed',
            $request->user(),
            'enrollment:'.$enrollment,
            ['count' => count($items)],
        );

        return response()->json([
            'data' => array_map(fn (EnrollmentSubjectDTO $dto): array => [
                'id' => $dto->id,
                'enrollment_id' => $dto->enrollmentId,
                'subject_id' => $dto->subjectId,
                'is_elective' => $dto->isElective,
                'status' => $dto->status,
            ], $items),
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function showSubject(
        ShowEnrollmentSubjectRequest $request,
        int $link,
        GetEnrollmentSubjectHandler $handler,
    ): JsonResponse {
        $dto = $handler->handle(new GetEnrollmentSubjectQuery(
            schoolId: $this->schoolContext->requireId(),
            linkId: $link,
        ));

        if ($dto === null) {
            return response()->json([
                'message' => 'Enrollment subject link not found.',
                'error_code' => 'enrollment.subject_link_not_found',
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], 404);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollments.subjects.show',
            'viewed',
            $request->user(),
            'enrollment_subject:'.$link,
            [],
        );

        return response()->json([
            'data' => [
                'id' => $dto->id,
                'enrollment_id' => $dto->enrollmentId,
                'subject_id' => $dto->subjectId,
                'is_elective' => $dto->isElective,
                'status' => $dto->status,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function deactivateSubject(
        DeactivateEnrollmentSubjectRequest $request,
        int $link,
        DeactivateEnrollmentSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new DeactivateEnrollmentSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            linkId: $link,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'enrollment.subject_deactivate_failed';

            return response()->json([
                'message' => 'Enrollment subject deactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'enrollment.subject_link_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.subjects.deactivate',
            'deactivated',
            $request->user(),
            'enrollment_subject:'.$result->linkId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'link_id' => $result->linkId,
                'status' => 2,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }

    public function reactivateSubject(
        ReactivateEnrollmentSubjectRequest $request,
        int $link,
        ReactivateEnrollmentSubjectHandler $handler,
    ): JsonResponse {
        $result = $handler->handle(new ReactivateEnrollmentSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            linkId: $link,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'enrollment.subject_reactivate_failed';

            return response()->json([
                'message' => 'Enrollment subject reactivate rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'enrollment.subject_link_not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.subjects.reactivate',
            'reactivated',
            $request->user(),
            'enrollment_subject:'.$result->linkId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return response()->json([
            'data' => [
                'link_id' => $result->linkId,
                'status' => 1,
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ]);
    }
}
