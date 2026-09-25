<?php

namespace App\Http\Controllers\Admission;

use App\Application\Admission\Commands\BulkTransitionApplicationStatusCommand;
use App\Application\Admission\Commands\BulkTransitionApplicationStatusHandler;
use App\Application\Admission\Commands\ChangeApplicationPeriodStatusCommand;
use App\Application\Admission\Commands\ChangeApplicationPeriodStatusHandler;
use App\Application\Admission\Commands\ConvertApplicationToStudentCommand;
use App\Application\Admission\Commands\ConvertApplicationToStudentHandler;
use App\Application\Admission\Commands\CreateApplicationDraftCommand;
use App\Application\Admission\Commands\CreateApplicationDraftHandler;
use App\Application\Admission\Commands\OpenApplicationPeriodCommand;
use App\Application\Admission\Commands\OpenApplicationPeriodHandler;
use App\Application\Admission\Commands\RegisterApplicationDocumentCommand;
use App\Application\Admission\Commands\RegisterApplicationDocumentHandler;
use App\Application\Admission\Commands\RegisterStudentViaAdmissionCommand;
use App\Application\Admission\Commands\RegisterStudentViaAdmissionHandler;
use App\Application\Admission\Commands\TransitionApplicationStatusCommand;
use App\Application\Admission\Commands\TransitionApplicationStatusHandler;
use App\Application\Admission\Commands\UpdateApplicationDraftCommand;
use App\Application\Admission\Commands\UpdateApplicationDraftHandler;
use App\Application\Admission\Commands\UpdateApplicationPeriodCommand;
use App\Application\Admission\Commands\UpdateApplicationPeriodHandler;
use App\Application\Admission\Queries\GetAdmissionWorkspaceHandler;
use App\Application\Admission\Queries\GetAdmissionWorkspaceQuery;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\ArchiveApplicationPeriodRequest;
use App\Http\Requests\Admission\BulkTransitionApplicationStatusRequest;
use App\Http\Requests\Admission\ChangeApplicationPeriodStatusRequest;
use App\Http\Requests\Admission\ConvertApplicationRequest;
use App\Http\Requests\Admission\CreateApplicationDraftRequest;
use App\Http\Requests\Admission\OpenApplicationPeriodRequest;
use App\Http\Requests\Admission\RegisterApplicationDocumentRequest;
use App\Http\Requests\Admission\RegisterStudentViaAdmissionRequest;
use App\Http\Requests\Admission\TransitionApplicationStatusRequest;
use App\Http\Requests\Admission\UpdateApplicationDraftRequest;
use App\Http\Requests\Admission\UpdateApplicationPeriodRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use App\Security\Policies\StudentPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AdmissionPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
        private readonly StudentPolicy $studentPolicy,
    ) {}

    public function index(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->workspacePage(
            $request,
            $handler,
            'admission/index',
            'admission.web.index',
            includeApplications: false,
        );
    }

    public function drafts(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->workspacePage(
            $request,
            $handler,
            'admission/drafts',
            'admission.web.drafts',
            statusFilter: 1,
        );
    }

    public function submitted(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->workspacePage(
            $request,
            $handler,
            'admission/submitted',
            'admission.web.submitted',
            statusFilter: 2,
        );
    }

    public function underReview(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->stageWorkspacePage(
            $request,
            $handler,
            status: 3,
            path: '/admission/under-review',
            labelKey: 'statusUnderReview',
            auditAction: 'admission.web.under_review',
        );
    }

    public function interview(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->stageWorkspacePage(
            $request,
            $handler,
            status: 4,
            path: '/admission/interview',
            labelKey: 'statusInterview',
            auditAction: 'admission.web.interview',
        );
    }

    public function waitlisted(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->stageWorkspacePage(
            $request,
            $handler,
            status: 5,
            path: '/admission/waitlisted',
            labelKey: 'statusWaitlisted',
            auditAction: 'admission.web.waitlisted',
        );
    }

    public function accepted(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->stageWorkspacePage(
            $request,
            $handler,
            status: 6,
            path: '/admission/accepted',
            labelKey: 'statusAccepted',
            auditAction: 'admission.web.accepted',
        );
    }

    public function withdrawn(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->stageWorkspacePage(
            $request,
            $handler,
            status: 8,
            path: '/admission/withdrawn',
            labelKey: 'statusWithdrawn',
            auditAction: 'admission.web.withdrawn',
        );
    }

    public function rejected(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        return $this->stageWorkspacePage(
            $request,
            $handler,
            status: 7,
            path: '/admission/rejected',
            labelKey: 'statusRejected',
            auditAction: 'admission.web.rejected',
        );
    }

    public function converted(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
        // Legacy route — conversion now lands on students; keep for deep links.
        return $this->stageWorkspacePage(
            $request,
            $handler,
            status: 9,
            path: '/admission/converted',
            labelKey: 'statusConverted',
            auditAction: 'admission.web.converted',
        );
    }

    private function stageWorkspacePage(
        Request $request,
        GetAdmissionWorkspaceHandler $handler,
        int $status,
        string $path,
        string $labelKey,
        string $auditAction,
    ): Response {
        return $this->workspacePage(
            $request,
            $handler,
            'admission/stage',
            $auditAction,
            [
                'stage' => [
                    'status' => $status,
                    'path' => $path,
                    'label_key' => $labelKey,
                ],
            ],
            statusFilter: $status,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function workspacePage(
        Request $request,
        GetAdmissionWorkspaceHandler $handler,
        string $component,
        string $auditAction,
        array $extra = [],
        ?int $statusFilter = null,
        bool $includeApplications = true,
    ): Response {
        $user = $request->user();
        assert($user !== null);

        if (! $user->can('viewAdmission')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 17)));

        $search = $request->filled('q')
            ? mb_substr(trim((string) $request->query('q')), 0, 80)
            : null;
        $search = $search === '' ? null : $search;

        $enrollmentStatus = null;
        if ($statusFilter === ApplicationStatus::Converted->value && $request->filled('enrollment_status')) {
            $rawEnrollmentStatus = trim((string) $request->query('enrollment_status'));
            if ($rawEnrollmentStatus === 'awaiting' || $rawEnrollmentStatus === 'completed') {
                $enrollmentStatus = $rawEnrollmentStatus;
            }
        }

        $workspace = $handler->handle(new GetAdmissionWorkspaceQuery(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            applicationPeriodId: $request->filled('application_period_id')
                ? (int) $request->query('application_period_id')
                : null,
            statusFilter: $statusFilter,
            includeApplications: $includeApplications,
            page: $page,
            perPage: $perPage,
            search: $search,
            enrollmentStatus: $enrollmentStatus,
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataAccess,
            $auditAction,
            'allowed',
            $user,
            'admission',
            [
                'academic_year_id' => $academicYearId,
                'status_filter' => $statusFilter,
                'page' => $workspace->pagination['page'] ?? $page,
            ],
        );

        return Inertia::render($component, array_merge([
            'workspace' => $workspace->toArray(),
            'filters' => [
                'academic_year_id' => $academicYearId,
                'application_period_id' => $workspace->selectedPeriodId ?? 0,
                'q' => $search,
                'enrollment_status' => $enrollmentStatus,
                'page' => $workspace->pagination['page'] ?? $page,
                'per_page' => $workspace->pagination['per_page'] ?? $perPage,
            ],
            'authorization' => [
                'can_manage' => $user->can('manageAdmission'),
                'can_update_student' => $this->studentPolicy->updateAny($user),
                'can_view_student_pii' => $this->studentPolicy->viewPii($user),
            ],
            'enrollmentFilterOptions' => $statusFilter === ApplicationStatus::Converted->value
                ? app(EnrollmentReadRepositoryInterface::class)->listFilterOptions($schoolId, $academicYearId)
                : [
                    'branches' => [],
                    'classes' => [],
                    'sections' => [],
                    'departments' => [],
                    'specializations' => [],
                    'grade_levels' => [],
                ],
        ], $extra));
    }

    public function storePeriod(
        OpenApplicationPeriodRequest $request,
        OpenApplicationPeriodHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new OpenApplicationPeriodCommand(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            name: (string) $request->validated('name'),
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
            maxApplications: $request->validated('max_applications'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.period.store',
            'created',
            $request->user(),
            "admission_period:{$result->periodId}",
        );

        return redirect()
            ->route('admission.index', ['academic_year_id' => $request->validated('academic_year_id')])
            ->with('success', 'تم فتح فترة التقديم.');
    }

    public function updatePeriod(
        UpdateApplicationPeriodRequest $request,
        int $period,
        UpdateApplicationPeriodHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = (int) $request->validated('academic_year_id');
        $result = $handler->handle(new UpdateApplicationPeriodCommand(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            periodId: $period,
            name: (string) $request->validated('name'),
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
            maxApplications: $request->validated('max_applications'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.period.update',
            'updated',
            $request->user(),
            "admission_period:{$result->periodId}",
        );

        return redirect()
            ->route('admission.index', ['academic_year_id' => $academicYearId])
            ->with('success', 'تم تحديث فترة التقديم.');
    }

    public function changePeriodStatus(
        ChangeApplicationPeriodStatusRequest $request,
        int $period,
        ChangeApplicationPeriodStatusHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = (int) $request->validated('academic_year_id');
        $result = $handler->handle(new ChangeApplicationPeriodStatusCommand(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            periodId: $period,
            status: (int) $request->validated('status'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.period.status',
            'updated',
            $request->user(),
            "admission_period:{$period}",
            [
                'from_status' => $result->fromStatus,
                'to_status' => $result->toStatus,
            ],
        );

        return redirect()
            ->route('admission.index', ['academic_year_id' => $academicYearId])
            ->with('success', 'تم تحديث حالة فترة التقديم.');
    }

    public function archivePeriod(
        ArchiveApplicationPeriodRequest $request,
        int $period,
        ChangeApplicationPeriodStatusHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $academicYearId = (int) $request->validated('academic_year_id');
        $result = $handler->handle(new ChangeApplicationPeriodStatusCommand(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            periodId: $period,
            status: ApplicationPeriodStatus::Archived->value,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.period.archive',
            'archived',
            $request->user(),
            "admission_period:{$period}",
            [
                'from_status' => $result->fromStatus,
                'to_status' => $result->toStatus,
            ],
        );

        return redirect()
            ->route('admission.index', ['academic_year_id' => $academicYearId])
            ->with('success', 'تم أرشفة فترة التقديم.');
    }

    public function storeApplication(
        CreateApplicationDraftRequest $request,
        CreateApplicationDraftHandler $handler,
        TransitionApplicationStatusHandler $transitionHandler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new CreateApplicationDraftCommand(
            schoolId: $schoolId,
            applicationPeriodId: (int) $request->validated('application_period_id'),
            firstName: (string) $request->validated('first_name'),
            fatherName: (string) $request->validated('father_name'),
            grandfatherName: (string) $request->validated('grandfather_name'),
            greatGrandfatherName: (string) $request->validated('great_grandfather_name'),
            lastName: (string) $request->validated('last_name'),
            motherName: (string) $request->validated('mother_name'),
            maternalFatherName: (string) $request->validated('maternal_father_name'),
            maternalGrandfatherName: (string) $request->validated('maternal_grandfather_name'),
            birthDate: (string) $request->validated('birth_date'),
            birthPlace: (string) $request->validated('birth_place'),
            gender: (int) $request->validated('gender'),
            targetSchoolId: (int) $request->validated('target_school_id'),
            intendedGradeName: (string) ($request->validated('intended_grade_name') ?? ''),
            nationalId: $request->validated('national_id'),
            gradeLevelId: $request->validated('grade_level_id') !== null
                ? (int) $request->validated('grade_level_id')
                : null,
            branchId: $request->validated('branch_id') !== null
                ? (int) $request->validated('branch_id')
                : null,
            branchName: $request->validated('branch_name') !== null && $request->validated('branch_name') !== ''
                ? (string) $request->validated('branch_name')
                : null,
            departmentName: $request->validated('department_name') !== null && $request->validated('department_name') !== ''
                ? (string) $request->validated('department_name')
                : null,
            specializationId: $request->validated('specialization_id') !== null
                ? (int) $request->validated('specialization_id')
                : null,
            specializationName: $request->validated('specialization_name') !== null && $request->validated('specialization_name') !== ''
                ? (string) $request->validated('specialization_name')
                : null,
            governorate: $request->validated('governorate'),
            administrativeUnit: $request->validated('administrative_unit') !== null
                ? (int) $request->validated('administrative_unit')
                : null,
            neighborhood: $request->validated('neighborhood'),
            fatherOccupation: $request->validated('father_occupation'),
            motherOccupation: $request->validated('mother_occupation'),
            studentMobile: $request->validated('student_mobile'),
            guardianMobile: $request->validated('guardian_mobile'),
            previousSchoolName: $request->validated('previous_school_name'),
            graduationYear: $request->validated('graduation_year') !== null
                ? (int) $request->validated('graduation_year')
                : null,
            previousGpa: $request->validated('previous_gpa') !== null
                ? (string) $request->validated('previous_gpa')
                : null,
            previousStudyTrack: $request->validated('previous_study_track') !== null
                ? (int) $request->validated('previous_study_track')
                : null,
            notes: $request->validated('notes'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.application.store',
            'created',
            $request->user(),
            "admission_application:{$result->applicationId}",
        );

        // Ribbon has no Draft stage — move new طلب قبول straight to مُرسل.
        $transitionHandler->handle(new TransitionApplicationStatusCommand(
            schoolId: $schoolId,
            applicationId: $result->applicationId,
            toStatus: ApplicationStatus::Submitted->value,
            reviewedBy: $request->user()?->id,
            notes: null,
            idempotencyKey: $request->header('X-Idempotency-Key') !== null
                ? $request->header('X-Idempotency-Key').':submit'
                : null,
        ));

        $academicYearId = $request->input('academic_year_id')
            ?? $request->query('academic_year_id');

        return redirect()
            ->route('admission.submitted', array_filter([
                'academic_year_id' => $academicYearId,
            ]))
            ->with('success', 'تم إنشاء الطلب وإرساله.');
    }

    public function registerStudent(
        RegisterStudentViaAdmissionRequest $request,
        RegisterStudentViaAdmissionHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RegisterStudentViaAdmissionCommand(
            schoolId: $schoolId,
            applicationPeriodId: (int) $request->validated('application_period_id'),
            firstName: (string) $request->validated('first_name'),
            fatherName: (string) $request->validated('father_name'),
            grandfatherName: (string) $request->validated('grandfather_name'),
            greatGrandfatherName: (string) $request->validated('great_grandfather_name'),
            lastName: (string) $request->validated('last_name'),
            motherName: (string) $request->validated('mother_name'),
            maternalFatherName: (string) $request->validated('maternal_father_name'),
            maternalGrandfatherName: (string) $request->validated('maternal_grandfather_name'),
            birthDate: (string) $request->validated('birth_date'),
            birthPlace: (string) $request->validated('birth_place'),
            gender: (int) $request->validated('gender'),
            targetSchoolId: (int) $request->validated('target_school_id'),
            intendedGradeName: (string) ($request->validated('intended_grade_name') ?? ''),
            nationalId: $request->validated('national_id'),
            gradeLevelId: $request->validated('grade_level_id') !== null
                ? (int) $request->validated('grade_level_id')
                : null,
            branchId: $request->validated('branch_id') !== null
                ? (int) $request->validated('branch_id')
                : null,
            branchName: $request->validated('branch_name') !== null && $request->validated('branch_name') !== ''
                ? (string) $request->validated('branch_name')
                : null,
            departmentName: $request->validated('department_name') !== null && $request->validated('department_name') !== ''
                ? (string) $request->validated('department_name')
                : null,
            specializationId: $request->validated('specialization_id') !== null
                ? (int) $request->validated('specialization_id')
                : null,
            specializationName: $request->validated('specialization_name') !== null && $request->validated('specialization_name') !== ''
                ? (string) $request->validated('specialization_name')
                : null,
            governorate: $request->validated('governorate'),
            administrativeUnit: $request->validated('administrative_unit') !== null
                ? (int) $request->validated('administrative_unit')
                : null,
            neighborhood: $request->validated('neighborhood'),
            fatherOccupation: $request->validated('father_occupation'),
            motherOccupation: $request->validated('mother_occupation'),
            studentMobile: $request->validated('student_mobile'),
            guardianMobile: $request->validated('guardian_mobile'),
            previousSchoolName: $request->validated('previous_school_name'),
            graduationYear: $request->validated('graduation_year') !== null
                ? (int) $request->validated('graduation_year')
                : null,
            previousGpa: $request->validated('previous_gpa') !== null
                ? (string) $request->validated('previous_gpa')
                : null,
            previousStudyTrack: $request->validated('previous_study_track') !== null
                ? (int) $request->validated('previous_study_track')
                : null,
            notes: $request->validated('notes'),
            reviewedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.application.register_student',
            'created',
            $request->user(),
            "admission_application:{$result->applicationId}",
            ['student_id' => $result->studentId],
        );

        return redirect()
            ->back()
            ->with('success', 'تم إنشاء الطالب من القبول.');
    }

    public function updateApplication(
        UpdateApplicationDraftRequest $request,
        int $application,
        UpdateApplicationDraftHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new UpdateApplicationDraftCommand(
            schoolId: $schoolId,
            applicationId: $application,
            notes: $request->validated('notes'),
            reviewedAt: $request->validated('reviewed_at'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.application.update',
            'updated',
            $request->user(),
            "admission_application:{$result->applicationId}",
        );

        return redirect()
            ->back()
            ->with('success', 'تم تحديث مسودة الطلب.');
    }

    public function transition(
        TransitionApplicationStatusRequest $request,
        int $application,
        TransitionApplicationStatusHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new TransitionApplicationStatusCommand(
            schoolId: $schoolId,
            applicationId: $application,
            toStatus: (int) $request->validated('to_status'),
            reviewedBy: $request->user()?->id,
            notes: $request->validated('notes'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.application.transition',
            'updated',
            $request->user(),
            "admission_application:{$application}",
            [
                'from_status' => $result->fromStatus,
                'to_status' => $result->toStatus,
            ],
        );

        $success = $result->toStatus === ApplicationStatus::Accepted->value
            ? 'تم قبول الطلب وتحويله إلى طالب.'
            : 'تم تحديث حالة الطلب.';

        return redirect()
            ->back()
            ->with('success', $success);
    }

    public function bulkTransition(
        BulkTransitionApplicationStatusRequest $request,
        BulkTransitionApplicationStatusHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        /** @var list<int> $applicationIds */
        $applicationIds = array_map('intval', $request->validated('application_ids'));
        $result = $handler->handle(new BulkTransitionApplicationStatusCommand(
            schoolId: $schoolId,
            applicationIds: $applicationIds,
            toStatus: (int) $request->validated('to_status'),
            reviewedBy: $request->user()?->id,
            notes: $request->validated('notes'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.application.bulk_transition',
            'updated',
            $request->user(),
            'admission_application:bulk',
            [
                'application_ids' => $result->applicationIds,
                'to_status' => $result->toStatus,
                'count' => $result->count,
            ],
        );

        $success = $result->toStatus === ApplicationStatus::Accepted->value
            ? ($result->count === 1
                ? 'تم قبول الطلب وتحويله إلى طالب.'
                : 'تم قبول الطلبات وتحويلها إلى طلاب.')
            : 'تم تحديث حالات الطلبات.';

        return redirect()
            ->back()
            ->with('success', $success);
    }

    public function convert(
        ConvertApplicationRequest $request,
        int $application,
        ConvertApplicationToStudentHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new ConvertApplicationToStudentCommand(
            schoolId: $schoolId,
            applicationId: $application,
            reviewedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.application.convert',
            'converted',
            $request->user(),
            "admission_application:{$application}",
            ['student_id' => $result->studentId],
        );

        return redirect()
            ->back()
            ->with('success', 'تم التحويل إلى طالب.');
    }

    public function storeDocument(
        RegisterApplicationDocumentRequest $request,
        int $application,
        RegisterApplicationDocumentHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $result = $handler->handle(new RegisterApplicationDocumentCommand(
            schoolId: $schoolId,
            applicationId: $application,
            documentType: (int) $request->validated('document_type'),
            fileName: (string) $request->validated('file_name'),
            storageKey: $request->validated('storage_key'),
            fileHash: $request->validated('file_hash'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataModified,
            'admission.web.document.store',
            'created',
            $request->user(),
            "admission_document:{$result->documentId}",
        );

        return redirect()
            ->back()
            ->with('success', 'تم تسجيل مستند الطلب.');
    }
}
