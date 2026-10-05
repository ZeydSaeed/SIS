<?php

namespace App\Http\Controllers\Student;

use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Organization\Queries\GetBranchStructureHandler;
use App\Application\Organization\Queries\GetBranchStructureQuery;
use App\Application\Student\Commands\ChangeStudentStatusesCommand;
use App\Application\Student\Commands\ChangeStudentStatusesHandler;
use App\Application\Student\Commands\CreateStudentHandler;
use App\Application\Student\Commands\UpdateStudentListRowCommand;
use App\Application\Student\Commands\UpdateStudentListRowHandler;
use App\Application\Student\Commands\UploadStudentDocumentCommand;
use App\Application\Student\Commands\UploadStudentDocumentHandler;
use App\Application\Student\Queries\GetStudentHandler;
use App\Application\Student\Queries\GetStudentQuery;
use App\Application\Student\Queries\ListStudentsHandler;
use App\Application\Student\Queries\ListStudentsQuery;
use App\Application\Student\Queries\SearchStudentsHandler;
use App\Application\Student\Queries\SearchStudentsQuery;
use App\Http\Controllers\Api\StudentProfileCommandFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\ChangeStudentStatusesRequest;
use App\Http\Requests\Student\CreateStudentRequest;
use App\Http\Requests\Student\UpdateStudentListRowRequest;
use App\Http\Requests\Student\UploadStudentDocumentRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Http\Support\WorkflowFlash;
use App\Infrastructure\Persistence\Eloquent\StudentRecord;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Authorization\Contracts\AuthorizationServiceInterface;
use App\Security\Authorization\Permission;
use App\Security\Authorization\StudentSchoolAccessService;
use App\Security\Context\SchoolContext;
use App\Security\Policies\StudentPolicy;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Support\StudentResponseSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(
        Request $request,
        ListStudentsHandler $listHandler,
        SearchStudentsHandler $searchHandler,
    ): Response {
        $this->authorize('viewAny', StudentRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $q = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', 17)), 100);
        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $gender = $this->queryGender($request);
        $enrolled = $this->queryEnrolled($request);
        $requestKind = $this->queryRequestKind($request);
        $academicYearId = $this->academicYears->resolve(
            $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null,
            $request,
        );

        if ($q !== '') {
            $result = $searchHandler->handle(new SearchStudentsQuery(
                term: $q,
                schoolId: $schoolId,
                page: $page,
                perPage: $perPage,
                status: $status,
                academicYearId: $academicYearId,
                gender: $gender,
                enrolled: $enrolled,
                requestKind: $requestKind,
            ));
        } else {
            $result = $listHandler->handle(new ListStudentsQuery(
                status: $status,
                schoolId: $schoolId,
                page: $page,
                perPage: $perPage,
                academicYearId: $academicYearId,
                gender: $gender,
                enrolled: $enrolled,
                requestKind: $requestKind,
            ));
        }

        $payload = $result->toArray();
        $payload['data'] = $this->sanitizer->sanitizeList($result->items, $user);

        $this->securityAudit->record(
            SecurityEventType::StudentDataAccess,
            'students.web.index',
            'allowed',
            $user,
            'students',
            ['page' => $page, 'per_page' => $perPage, 'search' => $q !== ''],
        );

        $authorization = $this->listAuthorization($user);
        $enrollmentFilterOptions = ($authorization['canEnroll'] && $request->boolean('include_enrollment_filters'))
            ? app(EnrollmentReadRepositoryInterface::class)->listFilterOptions($schoolId, $academicYearId)
            : [
                'branches' => [],
                'classes' => [],
                'sections' => [],
                'departments' => [],
                'specializations' => [],
                'grade_levels' => [],
            ];

        return Inertia::render('students/index', [
            'students' => $payload,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'page' => $page,
                'per_page' => $perPage,
                'academic_year_id' => $academicYearId,
                'gender' => $gender,
                'enrolled' => $enrolled === null ? null : ($enrolled ? 1 : 0),
                'request_kind' => $requestKind,
            ],
            'authorization' => $authorization,
            'enrollmentFilterOptions' => $enrollmentFilterOptions,
            // The school's own branches → departments for the student form lists.
            'schoolBranches' => fn (): array => $this->schoolBranches(),
        ]);
    }

    public function store(
        CreateStudentRequest $request,
        CreateStudentHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(StudentProfileCommandFactory::createFromRequest($request, $schoolId));

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.web.store',
            'created',
            $request->user(),
            "student:{$result->studentId}",
        );

        return WorkflowFlash::with(
            redirect()->route('enrollments.create', ['student_id' => $result->studentId]),
            [
                'tone' => 'warning',
                'action_href' => route('enrollments.create', ['student_id' => $result->studentId], absolute: false),
                'step' => 'student.created',
            ],
        );
    }

    public function bulkStatus(
        ChangeStudentStatusesRequest $request,
        ChangeStudentStatusesHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        /** @var list<int> $studentIds */
        $studentIds = array_map('intval', $request->validated('student_ids'));

        $result = $handler->handle(new ChangeStudentStatusesCommand(
            schoolId: $schoolId,
            studentIds: $studentIds,
            status: (int) $request->validated('status'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.web.bulk_status',
            'updated',
            $request->user(),
            'student:bulk',
            [
                'student_ids' => $result->studentIds,
                'status' => $result->status,
                'count' => $result->count,
            ],
        );

        return redirect()
            ->back()
            ->with('success', 'flash.studentStatusesUpdated');
    }

    public function update(
        UpdateStudentListRowRequest $request,
        int $student,
        UpdateStudentListRowHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $validated = $request->validated();
        $applyFormFields = array_key_exists('mother_name', $validated)
            || array_key_exists('governorate', $validated);
        $applyPii = array_key_exists('national_id', $validated)
            || array_key_exists('mobile', $validated)
            || array_key_exists('guardian_mobile', $validated)
            || array_key_exists('email', $validated);

        $result = $handler->handle(new UpdateStudentListRowCommand(
            studentId: $student,
            schoolId: $schoolId,
            firstName: (string) $validated['first_name'],
            lastName: (string) $validated['last_name'],
            birthDate: (string) $validated['birth_date'],
            presentFields: array_keys($validated),
            fatherName: $this->nullableString($validated, 'father_name'),
            grandfatherName: $this->nullableString($validated, 'grandfather_name'),
            greatGrandfatherName: $this->nullableString($validated, 'great_grandfather_name'),
            departmentName: $this->nullableString($validated, 'department_name'),
            admittedClassName: $this->nullableString($validated, 'admitted_class_name'),

            applyFormFields: $applyFormFields,
            applyPii: $applyPii,
            motherName: $this->nullableString($validated, 'mother_name'),
            maternalFatherName: $this->nullableString($validated, 'maternal_father_name'),
            maternalGrandfatherName: $this->nullableString($validated, 'maternal_grandfather_name'),
            guardianTripleName: $this->nullableString($validated, 'guardian_triple_name'),
            governorate: $this->nullableString($validated, 'governorate'),
            neighborhood: $this->nullableString($validated, 'neighborhood'),
            locality: $this->nullableString($validated, 'locality'),
            houseNumber: $this->nullableString($validated, 'house_number'),
            birthPlace: $this->nullableString($validated, 'birth_place'),
            registrationPlace: $this->nullableString($validated, 'registration_place'),
            gender: $this->nullableInt($validated, 'gender'),
            nationality: $this->nullableString($validated, 'nationality'),
            religion: $this->nullableInt($validated, 'religion'),
            mawalidDate: $this->nullableString($validated, 'mawalid_date'),
            previousSchoolName: $this->nullableString($validated, 'previous_school_name'),
            transferDocumentNumber: $this->nullableInt($validated, 'transfer_document_number'),
            transferDocumentDate: $this->nullableString($validated, 'transfer_document_date'),
            schoolStartDate: $this->nullableString($validated, 'school_start_date'),
            notes: $this->nullableString($validated, 'notes'),
            schoolName: $this->nullableString($validated, 'school_name'),
            branchId: $this->nullableInt($validated, 'branch_id'),

            fatherOccupation: $this->nullableString($validated, 'father_occupation'),
            motherOccupation: $this->nullableString($validated, 'mother_occupation'),
            administrativeUnit: $this->nullableInt($validated, 'administrative_unit'),
            graduationYear: $this->nullableInt($validated, 'graduation_year'),
            previousGpa: isset($validated['previous_gpa']) && $validated['previous_gpa'] !== null && $validated['previous_gpa'] !== ''
                ? (float) $validated['previous_gpa'] : null,
            previousStudyTrack: $this->nullableInt($validated, 'previous_study_track'),
            mathematicsGrade: isset($validated['mathematics_grade']) && $validated['mathematics_grade'] !== null && $validated['mathematics_grade'] !== ''
                ? (float) $validated['mathematics_grade'] : null,
            physicsGrade: isset($validated['physics_grade']) && $validated['physics_grade'] !== null && $validated['physics_grade'] !== ''
                ? (float) $validated['physics_grade'] : null,
            nationalId: $this->nullableString($validated, 'national_id'),
            mobile: $this->nullableString($validated, 'mobile'),
            guardianMobile: $this->nullableString($validated, 'guardian_mobile'),
            email: $this->nullableString($validated, 'email'),
            admittedAcademicYearId: $this->nullableInt($validated, 'academic_year_id'),
        ));

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.web.update',
            'updated',
            $request->user(),
            "student:{$result->studentId}",
        );

        return redirect()
            ->back()
            ->with('success', 'flash.studentUpdated');
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
        $academicYearId = $this->academicYears->resolve(
            $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null,
            $request,
        );
        $detail = $handler->handle(new GetStudentQuery($student, $schoolId, $academicYearId));

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
            'schoolBranches' => fn (): array => $this->schoolBranches(),
        ]);
    }

    /**
     * Active branches (الفروع) of the current school with their active departments (الاختصاصات).
     *
     * @return list<array<string, mixed>>
     */
    private function schoolBranches(): array
    {
        return app(GetBranchStructureHandler::class)->handle(
            new GetBranchStructureQuery($this->schoolContext->requireId()),
        );
    }

    public function uploadDocument(
        int $student,
        UploadStudentDocumentRequest $request,
        UploadStudentDocumentHandler $handler,
    ): JsonResponse {
        $file = $request->file('file');
        /** @var list<string> $allowedMimes */
        $allowedMimes = config('sis.documents.allowed_mimes', [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'image/webp',
            'text/plain',
        ]);

        $result = $handler->handle(new UploadStudentDocumentCommand(
            schoolId: $this->schoolContext->requireId(),
            studentId: $student,
            documentType: (int) $request->validated('document_type'),
            fileName: (string) $file->getClientOriginalName(),
            mimeType: (string) ($file->getMimeType() ?: 'application/octet-stream'),
            contents: (string) file_get_contents($file->getRealPath()),
            maxBytes: (int) config('sis.documents.max_bytes', 10 * 1024 * 1024),
            allowedMimes: $allowedMimes,
            uploadedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        if ($result->failed()) {
            $code = $result->errors[0] ?? 'student.document_upload_failed';

            return response()->json([
                'message' => 'Student document upload rejected.',
                'error_code' => $code,
                'meta' => ['correlation_id' => CorrelationContext::id()],
            ], $code === 'student.not_found' ? 404 : 422);
        }

        $this->securityAudit->record(
            SecurityEventType::StudentDataModified,
            'students.web.documents.binary.upload',
            'uploaded',
            $request->user(),
            'student:'.$student,
            [
                'from_idempotency' => $result->fromIdempotencyCache,
                'storage_key' => $result->storageKey,
            ],
        );

        return response()->json([
            'data' => [
                'document_id' => $result->documentId,
                'storage_key' => $result->storageKey,
                'file_name' => (string) $file->getClientOriginalName(),
                'document_type' => (int) $request->validated('document_type'),
                'from_idempotency' => $result->fromIdempotencyCache,
            ],
            'meta' => ['correlation_id' => CorrelationContext::id()],
        ], $result->fromIdempotencyCache ? 200 : 201);
    }

    private function queryGender(Request $request): ?int
    {
        if (! $request->filled('gender')) {
            return null;
        }

        $gender = (int) $request->query('gender');

        return $gender === 1 || $gender === 2 ? $gender : null;
    }

    private function queryEnrolled(Request $request): ?bool
    {
        if (! $request->filled('enrolled')) {
            return null;
        }

        $value = $request->query('enrolled');

        if ($value === '1' || $value === 1 || $value === true || $value === 'true') {
            return true;
        }

        if ($value === '0' || $value === 0 || $value === false || $value === 'false') {
            return false;
        }

        return null;
    }

    private function queryRequestKind(Request $request): ?int
    {
        if (! $request->filled('request_kind')) {
            return null;
        }

        $kind = (int) $request->query('request_kind');

        return $kind === 1 || $kind === 2 ? $kind : null;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableString(array $validated, string $key): ?string
    {
        if (! array_key_exists($key, $validated) || $validated[$key] === null || $validated[$key] === '') {
            return null;
        }

        return (string) $validated[$key];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function nullableInt(array $validated, string $key): ?int
    {
        if (! array_key_exists($key, $validated) || $validated[$key] === null || $validated[$key] === '') {
            return null;
        }

        return (int) $validated[$key];
    }

    /**
     * @return array{canView: bool, canViewPii: bool, canUpdate: bool, canCreate: bool, canEnroll: bool}
     */
    private function listAuthorization(User $user): array
    {
        return [
            'canView' => true,
            'canViewPii' => $this->studentPolicy->viewPii($user),
            'canUpdate' => $this->authorization->userHasPermission($user, Permission::STUDENTS_UPDATE)
                && $this->schoolAccess->canAccessStudent($user),
            'canCreate' => $this->studentPolicy->create($user)
                && $this->schoolAccess->canAccessStudent($user),
            'canEnroll' => $this->authorization->userHasPermission($user, Permission::ENROLLMENT_CREATE)
                && $this->schoolAccess->canAccessStudent($user),
        ];
    }

    /**
     * @return array{canView: bool, canViewPii: bool, canUpdate: bool, canCreate: bool, canEnroll: bool}
     */
    private function recordAuthorization(User $user, int $studentId): array
    {
        return [
            'canView' => $this->studentPolicy->view($user, $studentId),
            'canViewPii' => $this->studentPolicy->viewPii($user),
            'canUpdate' => $this->studentPolicy->update($user, $studentId),
            'canCreate' => $this->studentPolicy->create($user),
            'canEnroll' => $this->authorization->userHasPermission($user, Permission::ENROLLMENT_CREATE)
                && $this->schoolAccess->canAccessStudent($user),
        ];
    }
}
