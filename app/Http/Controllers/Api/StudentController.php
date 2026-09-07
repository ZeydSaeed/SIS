<?php

namespace App\Http\Controllers\Api;

use App\Application\Student\Commands\CreateStudentCommand;
use App\Application\Student\Commands\CreateStudentHandler;
use App\Application\Student\Commands\UpdateStudentCommand;
use App\Application\Student\Commands\UpdateStudentHandler;
use App\Application\Student\Queries\GetStudentHandler;
use App\Application\Student\Queries\GetStudentQuery;
use App\Application\Student\Queries\ListStudentsHandler;
use App\Application\Student\Queries\ListStudentsQuery;
use App\Application\Student\Queries\SearchStudentsHandler;
use App\Application\Student\Queries\SearchStudentsQuery;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\UpdateStudentRequest;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use App\Security\Support\StudentResponseSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentResponseSanitizer $sanitizer,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly SchoolContext $schoolContext,
    ) {}

    public function index(Request $request, ListStudentsHandler $handler): JsonResponse
    {
        $this->authorize('viewAny', StudentRecord::class);

        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(new ListStudentsQuery(
            status: $status,
            schoolId: $schoolId,
            page: $page,
            perPage: $perPage,
        ));

        $payload = $result->toArray();
        $payload['data'] = $this->sanitizer->sanitizeList($result->items);

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.index',
            'allowed',
            $request->user(),
            'students',
            ['page' => $page, 'per_page' => $perPage],
        );

        return response()->json($payload);
    }

    public function search(Request $request, SearchStudentsHandler $handler): JsonResponse
    {
        $this->authorize('viewAny', StudentRecord::class);

        $term = (string) $request->query('q', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(new SearchStudentsQuery(
            term: $term,
            schoolId: $schoolId,
            page: $page,
            perPage: $perPage,
        ));

        $payload = $result->toArray();
        $payload['data'] = $this->sanitizer->sanitizeList($result->items);

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.search',
            'allowed',
            $request->user(),
            'students',
            ['term_length' => strlen($term)],
        );

        return response()->json($payload);
    }

    public function show(Request $request, int $student, GetStudentHandler $handler): JsonResponse
    {
        $record = StudentRecord::query()->find($student);

        if ($record === null) {
            throw StudentNotFoundException::forId($student);
        }

        try {
            $this->authorize('view', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'students.show',
                'denied',
                $request->user(),
                "student:{$student}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $detail = $handler->handle(new GetStudentQuery($student, $schoolId));

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.show',
            'allowed',
            $request->user(),
            "student:{$student}",
        );

        return response()->json([
            'data' => $this->sanitizer->sanitizeDetail($detail, $request->user()),
        ]);
    }

    public function store(
        CreateStudentRequest $request,
        CreateStudentHandler $handler,
    ): JsonResponse {
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(new CreateStudentCommand(
            firstName: $request->validated('first_name'),
            middleName: $request->validated('middle_name'),
            lastName: $request->validated('last_name'),
            gender: (int) $request->validated('gender'),
            birthDate: $request->validated('birth_date'),
            studentCode: $request->validated('student_code'),
            nationalId: $request->validated('national_id'),
            birthPlace: $request->validated('birth_place'),
            nationality: $request->validated('nationality'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
            schoolId: $schoolId,
        ));

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.store',
            'created',
            $request->user(),
            "student:{$result->studentId}",
        );

        return response()->json([
            'data' => [
                'id' => $result->studentId,
                'student_code' => $result->studentCode,
            ],
            'meta' => [
                'from_idempotency_cache' => $result->fromIdempotencyCache,
                'correlation_id' => CorrelationContext::id(),
            ],
        ], 201);
    }

    public function update(
        UpdateStudentRequest $request,
        int $student,
        UpdateStudentHandler $handler,
    ): JsonResponse {
        $record = StudentRecord::query()->find($student);

        try {
            $this->authorize('update', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'students.update',
                'denied',
                $request->user(),
                "student:{$student}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }

        $result = $handler->handle(new UpdateStudentCommand(
            studentId: $student,
            firstName: $request->validated('first_name'),
            middleName: $request->validated('middle_name'),
            lastName: $request->validated('last_name'),
            gender: (int) $request->validated('gender'),
            birthDate: $request->validated('birth_date'),
            nationalId: $request->validated('national_id'),
            birthPlace: $request->validated('birth_place'),
            nationality: $request->validated('nationality'),
        ));

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.update',
            'updated',
            $request->user(),
            "student:{$student}",
        );

        return response()->json([
            'data' => [
                'id' => $result->studentId,
            ],
            'meta' => [
                'correlation_id' => CorrelationContext::id(),
            ],
        ]);
    }
}
