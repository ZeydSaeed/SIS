<?php

namespace App\Http\Controllers\Enrollment;

use App\Application\Enrollment\Commands\BulkUpdateEnrollmentPlacementCommand;
use App\Application\Enrollment\Commands\BulkUpdateEnrollmentPlacementHandler;
use App\Application\Enrollment\Commands\ChangeEnrollmentStatusesCommand;
use App\Application\Enrollment\Commands\ChangeEnrollmentStatusesHandler;
use App\Application\Enrollment\Commands\EnrollStudentCommand;
use App\Application\Enrollment\Commands\EnrollStudentHandler;
use App\Application\Enrollment\Commands\UpdateEnrollmentPlacementCommand;
use App\Application\Enrollment\Commands\UpdateEnrollmentPlacementHandler;
use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Enrollment\Queries\GetEnrollmentHandler;
use App\Application\Enrollment\Queries\GetEnrollmentQuery;
use App\Application\Enrollment\Queries\ListEnrollmentsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentsQuery;
use App\Application\Student\Queries\GetStudentHandler;
use App\Application\Student\Queries\GetStudentQuery;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\BulkUpdateEnrollmentPlacementRequest;
use App\Http\Requests\Enrollment\ChangeEnrollmentStatusesRequest;
use App\Http\Requests\Enrollment\EnrollStudentRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentPlacementRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\EnrollmentSchoolAccessService;
use App\Security\Authorization\Permission;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class EnrollmentPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
        private readonly AuthorizationServiceInterface $authorization,
        private readonly EnrollmentSchoolAccessService $schoolAccess,
    ) {}

    public function index(
        Request $request,
        ListEnrollmentsHandler $handler,
        EnrollmentReadRepositoryInterface $enrollments,
    ): Response {
        $this->authorize('viewAny', EnrollmentRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $q = trim((string) $request->query('q', ''));
        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $gender = $this->queryGender($request);
        $classId = $request->filled('class_id') ? (int) $request->query('class_id') : null;
        $sectionId = $request->filled('section_id') ? (int) $request->query('section_id') : null;
        $departmentName = $request->filled('department_name')
            ? trim((string) $request->query('department_name'))
            : null;
        $departmentName = $departmentName === '' ? null : $departmentName;
        $specializationId = $request->filled('specialization_id')
            ? (int) $request->query('specialization_id')
            : null;
        $branchId = $request->filled('branch_id') ? (int) $request->query('branch_id') : null;
        $departmentId = $request->filled('department_id')
            ? (int) $request->query('department_id')
            : null;
        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear, $request);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 15)), 100);

        $result = $handler->handle(new ListEnrollmentsQuery(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            page: $page,
            perPage: $perPage,
            status: $status,
            q: $q,
            gender: $gender,
            classId: $classId,
            sectionId: $sectionId,
            departmentName: $departmentName,
            specializationId: $specializationId,
            branchId: $branchId,
            departmentId: $departmentId,
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollments.web.index',
            'allowed',
            $user,
            'enrollments',
            [
                'page' => $page,
                'per_page' => $perPage,
                'academic_year_id' => $academicYearId,
                'search' => $q !== '',
            ],
        );

        return Inertia::render('enrollments/index', [
            'enrollments' => $result->toArray(),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'gender' => $gender,
                'class_id' => $classId,
                'section_id' => $sectionId,
                'department_name' => $departmentName,
                'specialization_id' => $specializationId,
                'branch_id' => $branchId,
                'department_id' => $departmentId,
                'academic_year_id' => $academicYearId,
                'page' => $page,
                'per_page' => $perPage,
            ],
            'filterOptions' => $enrollments->listFilterOptions($schoolId, $academicYearId, $classId),
            'authorization' => $this->listAuthorization($user),
        ]);
    }

    public function bulkStatus(
        ChangeEnrollmentStatusesRequest $request,
        ChangeEnrollmentStatusesHandler $handler,
    ): RedirectResponse {
        $user = $request->user();
        assert($user !== null);
        $status = (int) $request->validated('status');
        $auth = $this->listAuthorization($user);

        if ($status === 1 && ! $auth['canUpdate'] && ! $auth['canCancel']) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        if (in_array($status, [0, 2, 3, 4], true) && ! $auth['canCancel']) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        /** @var list<int> $enrollmentIds */
        $enrollmentIds = array_map('intval', $request->validated('enrollment_ids'));
        $effectiveTo = $request->validated('effective_to') ?? now()->toDateString();

        $result = $handler->handle(new ChangeEnrollmentStatusesCommand(
            schoolId: $schoolId,
            enrollmentIds: $enrollmentIds,
            status: $status,
            effectiveTo: (string) $effectiveTo,
            actedBy: $user->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.web.bulk_status',
            'updated',
            $user,
            'enrollment:bulk',
            [
                'enrollment_ids' => $result->enrollmentIds,
                'skipped_ids' => $result->skippedIds,
                'status' => $result->status,
                'count' => $result->count,
            ],
        );

        return redirect()->back();
    }

    public function bulkPlacement(
        BulkUpdateEnrollmentPlacementRequest $request,
        BulkUpdateEnrollmentPlacementHandler $handler,
    ): RedirectResponse {
        $user = $request->user();
        assert($user !== null);
        $auth = $this->listAuthorization($user);
        if (! $auth['canUpdate']) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        /** @var list<int> $enrollmentIds */
        $enrollmentIds = array_map('intval', $request->validated('enrollment_ids'));

        $result = $handler->handle(new BulkUpdateEnrollmentPlacementCommand(
            schoolId: $schoolId,
            enrollmentIds: $enrollmentIds,
            classId: $request->exists('class_id') ? (int) $request->validated('class_id') : null,
            sectionId: $request->exists('section_id') ? (int) $request->validated('section_id') : null,
            branchId: $request->exists('branch_id') ? $request->validated('branch_id') : null,
            departmentId: $request->exists('department_id') ? $request->validated('department_id') : null,
            specializationId: $request->exists('specialization_id')
                ? $request->validated('specialization_id')
                : null,
            gender: $request->exists('gender') ? (int) $request->validated('gender') : null,
            updateClass: $request->exists('class_id'),
            updateSection: $request->exists('section_id'),
            updateBranch: $request->exists('branch_id'),
            updateDepartment: $request->exists('department_id'),
            updateSpecialization: $request->exists('specialization_id'),
            updateGender: $request->exists('gender'),
            allowInactive: (bool) ($request->validated('allow_inactive') ?? false),
            updatedBy: $user->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.web.bulk_placement',
            'updated',
            $user,
            'enrollment:bulk',
            [
                'enrollment_ids' => $result->enrollmentIds,
                'skipped_ids' => $result->skippedIds,
                'class_id' => $result->classId,
                'section_id' => $result->sectionId,
                'count' => $result->count,
            ],
        );

        return redirect()->back();
    }

    public function show(Request $request, int $enrollment, GetEnrollmentHandler $handler): Response
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
                'enrollments.web.show',
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
            'enrollments.web.show',
            'allowed',
            $request->user(),
            "enrollment:{$enrollment}",
        );

        return Inertia::render('enrollments/show', [
            'enrollment' => $detail->toArray(),
        ]);
    }

    public function create(
        Request $request,
        GetStudentHandler $students,
        EnrollmentReadRepositoryInterface $enrollments,
    ): Response {
        $this->authorize('create', EnrollmentRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $studentId = $request->filled('student_id') ? (int) $request->query('student_id') : null;
        $studentPayload = null;
        $intent = [
            'branch_id' => $request->filled('branch_id') ? (int) $request->query('branch_id') : null,
            'department_id' => $request->filled('department_id') ? (int) $request->query('department_id') : null,
            'department_name' => $request->filled('department_name')
                ? trim((string) $request->query('department_name'))
                : null,
            'specialization_id' => $request->filled('specialization_id')
                ? (int) $request->query('specialization_id')
                : null,
            'grade_level_id' => $request->filled('grade_level_id')
                ? (int) $request->query('grade_level_id')
                : null,
        ];
        if ($intent['department_name'] === '') {
            $intent['department_name'] = null;
        }

        if ($studentId !== null) {
            try {
                $student = $students->handle(new GetStudentQuery($studentId, $schoolId, $academicYearId));
                if ($student->academicYearId !== null) {
                    $academicYearId = $student->academicYearId;
                }
                $studentPayload = [
                    'id' => $student->id,
                    'student_code' => $student->studentCode,
                    'full_name' => $student->fullName,
                    'first_name' => $student->firstName,
                    'father_name' => $student->fatherName,
                    'grandfather_name' => $student->grandfatherName,
                    'great_grandfather_name' => $student->greatGrandfatherName,
                    'last_name' => $student->lastName,
                    'gender' => $student->gender,
                    'birth_date' => $student->birthDate,
                ];
            } catch (StudentNotFoundException) {
                $studentId = null;
            }
        }

        $filterOptions = $enrollments->listFilterOptions($schoolId, $academicYearId);

        if ($intent['department_id'] === null && $intent['department_name'] !== null) {
            foreach ($filterOptions['departments'] as $department) {
                if (strcasecmp((string) $department['name'], $intent['department_name']) === 0) {
                    $intent['department_id'] = (int) $department['id'];
                    if ($intent['branch_id'] === null && $department['branch_id'] !== null) {
                        $intent['branch_id'] = (int) $department['branch_id'];
                    }
                    break;
                }
            }
        }

        $suggestedClassId = null;
        if ($intent['grade_level_id'] !== null) {
            $matches = array_values(array_filter(
                $filterOptions['classes'],
                static fn (array $class): bool => (int) ($class['grade_level_id'] ?? 0) === $intent['grade_level_id'],
            ));
            if (count($matches) === 1) {
                $suggestedClassId = (int) $matches[0]['id'];
            }
        }

        return Inertia::render('enrollments/create', [
            'defaults' => [
                'academic_year_id' => $academicYearId,
                'student_id' => $studentId,
                'effective_from' => now()->toDateString(),
                'branch_id' => $intent['branch_id'],
                'department_id' => $intent['department_id'],
                'specialization_id' => $intent['specialization_id'],
                'class_id' => $suggestedClassId,
            ],
            'student' => $studentPayload,
            'filterOptions' => $filterOptions,
            'needsEnrollment' => $studentId !== null,
        ]);
    }

    public function store(EnrollStudentRequest $request, EnrollStudentHandler $handler): RedirectResponse
    {
        $schoolId = $this->schoolContext->requireId();
        $studentId = (int) $request->validated('student_id');
        $academicYearId = (int) $request->validated('academic_year_id');

        $result = $handler->handle(new EnrollStudentCommand(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            studentId: $studentId,
            classId: (int) $request->validated('class_id'),
            sectionId: (int) $request->validated('section_id'),
            effectiveFrom: $request->validated('effective_from'),
            specializationId: $request->validated('specialization_id'),
            branchId: $request->validated('branch_id'),
            departmentId: $request->validated('department_id'),
            enrolledBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.web.store',
            'created',
            $request->user(),
            "enrollment:{$result->enrollmentId}",
        );

        return redirect()->route('enrollments.index', [
            'academic_year_id' => $academicYearId,
        ]);
    }

    public function edit(Request $request, int $enrollment, GetEnrollmentHandler $handler): Response
    {
        $record = EnrollmentRecord::query()->find($enrollment);
        if ($record === null) {
            throw EnrollmentNotFoundException::forId($enrollment);
        }

        try {
            $this->authorize('update', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'enrollments.web.edit',
                'denied',
                $request->user(),
                "enrollment:{$enrollment}",
            );
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $detail = $handler->handle(new GetEnrollmentQuery($enrollment, $schoolId));

        return Inertia::render('enrollments/edit', [
            'enrollment' => $detail->toArray(),
        ]);
    }

    public function update(
        UpdateEnrollmentPlacementRequest $request,
        int $enrollment,
        UpdateEnrollmentPlacementHandler $handler,
    ): RedirectResponse {
        $record = EnrollmentRecord::query()->find($enrollment);
        if ($record === null) {
            throw EnrollmentNotFoundException::forId($enrollment);
        }

        try {
            $this->authorize('update', $record);
        } catch (AuthorizationException) {
            $this->securityAudit->record(
                SecurityEventType::IdorBlocked,
                'enrollments.web.update',
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
            branchId: $request->exists('branch_id') ? $request->validated('branch_id') : null,
            updateBranch: $request->exists('branch_id'),
            departmentId: $request->exists('department_id') ? $request->validated('department_id') : null,
            updateDepartment: $request->exists('department_id'),
            effectiveFrom: $request->validated('effective_from'),
            academicYearId: $request->exists('academic_year_id')
                ? (int) $request->validated('academic_year_id')
                : null,
            effectiveTo: $request->validated('effective_to'),
            clearEffectiveTo: (bool) ($request->validated('clear_effective_to') ?? false),
            stageName: $request->exists('stage_name') ? $request->validated('stage_name') : null,
            updateStage: $request->exists('stage_name'),
            gender: $request->exists('gender') ? (int) $request->validated('gender') : null,
            updatedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'enrollments.web.update',
            'updated',
            $request->user(),
            "enrollment:{$enrollment}",
            [
                'class_id' => $result->classId,
                'section_id' => $result->sectionId,
            ],
        );

        return redirect()->back();
    }

    private function queryGender(Request $request): ?int
    {
        if (! $request->filled('gender')) {
            return null;
        }

        $gender = (int) $request->query('gender');

        return $gender === 1 || $gender === 2 ? $gender : null;
    }

    /**
     * @return array{canView: bool, canCreate: bool, canUpdate: bool, canCancel: bool}
     */
    private function listAuthorization(User $user): array
    {
        $canAccess = $this->schoolAccess->canAccessEnrollment($user);

        return [
            'canView' => $this->authorization->userHasPermission($user, Permission::ENROLLMENT_VIEW)
                && $canAccess,
            'canCreate' => $this->authorization->userHasPermission($user, Permission::ENROLLMENT_CREATE)
                && $canAccess,
            'canUpdate' => $this->authorization->userHasPermission($user, Permission::ENROLLMENT_UPDATE)
                && $canAccess,
            'canCancel' => $this->authorization->userHasPermission($user, Permission::ENROLLMENT_CANCEL)
                && $canAccess,
        ];
    }
}
