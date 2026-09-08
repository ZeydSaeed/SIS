<?php

namespace App\Http\Controllers\Student;

use App\Application\Student\Queries\GetStudentHandler;
use App\Application\Student\Queries\GetStudentQuery;
use App\Application\Student\Queries\ListStudentsHandler;
use App\Application\Student\Queries\ListStudentsQuery;
use App\Application\Student\Queries\SearchStudentsHandler;
use App\Application\Student\Queries\SearchStudentsQuery;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\StudentSchoolAccessService;
use App\Security\Context\SchoolContext;
use App\Security\Policies\StudentPolicy;
use App\Security\Support\StudentResponseSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly StudentResponseSanitizer $sanitizer,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AuthorizationServiceInterface $authorization,
        private readonly StudentSchoolAccessService $schoolAccess,
        private readonly StudentPolicy $studentPolicy,
    ) {}

    public function index(
        Request $request,
        ListStudentsHandler $listHandler,
        SearchStudentsHandler $searchHandler,
        GetStudentHandler $getHandler,
    ): Response {
        $this->authorize('viewAny', StudentRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 25)), 100);
        $status = $request->filled('status') ? (int) $request->query('status') : null;

        if ($q !== '') {
            $result = $searchHandler->handle(new SearchStudentsQuery(
                term: $q,
                schoolId: $schoolId,
                page: $page,
                perPage: $perPage,
            ));
        } else {
            $result = $listHandler->handle(new ListStudentsQuery(
                status: $status,
                schoolId: $schoolId,
                page: $page,
                perPage: $perPage,
            ));
        }

        $payload = $result->toArray();
        $payload['data'] = $this->sanitizer->sanitizeList($result->items);

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.web.index',
            'allowed',
            $user,
            'students',
            ['page' => $page, 'per_page' => $perPage, 'search' => $q !== ''],
        );

        $props = [
            'students' => $payload,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'page' => $page,
                'per_page' => $perPage,
            ],
            'authorization' => $this->listAuthorization($user),
            'preview' => null,
        ];

        if ($request->filled('student')) {
            $props['preview'] = $this->resolvePreview($request, (int) $request->query('student'), $getHandler);
        }

        return Inertia::render('students/index', $props);
    }

    public function show(Request $request, int $student, GetStudentHandler $handler): Response
    {
        $user = $request->user();
        assert($user !== null);

        if (! $this->studentPolicy->view($user, $student)) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'students.web.show',
                'denied',
                $user,
                "student:{$student}",
            );

            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $detail = $handler->handle(new GetStudentQuery($student, $schoolId));

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.web.show',
            'allowed',
            $user,
            "student:{$student}",
        );

        return Inertia::render('students/show', [
            'student' => $this->sanitizer->sanitizeDetail($detail, $user),
            'authorization' => $this->recordAuthorization($user, $student),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolvePreview(Request $request, int $studentId, GetStudentHandler $handler): ?array
    {
        $user = $request->user();
        assert($user !== null);

        if (! $this->studentPolicy->view($user, $studentId)) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'students.web.preview',
                'denied',
                $user,
                "student:{$studentId}",
            );

            return ['error' => 'forbidden'];
        }

        $schoolId = $this->schoolContext->requireId();
        $detail = $handler->handle(new GetStudentQuery($studentId, $schoolId));

        return [
            'student' => $this->sanitizer->sanitizeDetail($detail, $user),
            'authorization' => $this->recordAuthorization($user, $studentId),
        ];
    }

    /**
     * @return array{canView: bool, canViewPii: bool, canUpdate: bool}
     */
    private function listAuthorization(User $user): array
    {
        return [
            'canView' => true,
            'canViewPii' => $this->studentPolicy->viewPii($user),
            'canUpdate' => $this->authorization->userHasPermission($user, Permission::STUDENTS_UPDATE)
                && $this->schoolAccess->canAccessStudent($user),
        ];
    }

    /**
     * @return array{canView: bool, canViewPii: bool, canUpdate: bool}
     */
    private function recordAuthorization(User $user, int $studentId): array
    {
        return [
            'canView' => $this->studentPolicy->view($user, $studentId),
            'canViewPii' => $this->studentPolicy->viewPii($user),
            'canUpdate' => $this->studentPolicy->update($user, $studentId),
        ];
    }
}
