<?php

namespace App\Http\Controllers\Api;

use App\Application\Enrollment\Commands\EnrollStudentCommand;
use App\Application\Enrollment\Commands\EnrollStudentHandler;
use App\Application\Enrollment\Queries\GetEnrollmentHandler;
use App\Application\Enrollment\Queries\GetEnrollmentQuery;
use App\Application\Enrollment\Queries\ListEnrollmentsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentsQuery;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\EnrollStudentRequest;
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

        $result = $handler->handle(new ListEnrollmentsQuery(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            page: $page,
            perPage: $perPage,
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
}
