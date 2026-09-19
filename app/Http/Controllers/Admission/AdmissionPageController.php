<?php

namespace App\Http\Controllers\Admission;

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
use App\Application\Admission\Commands\TransitionApplicationStatusCommand;
use App\Application\Admission\Commands\TransitionApplicationStatusHandler;
use App\Application\Admission\Commands\UpdateApplicationPeriodCommand;
use App\Application\Admission\Commands\UpdateApplicationPeriodHandler;
use App\Application\Admission\Queries\GetAdmissionWorkspaceHandler;
use App\Application\Admission\Queries\GetAdmissionWorkspaceQuery;
use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admission\ArchiveApplicationPeriodRequest;
use App\Http\Requests\Admission\ChangeApplicationPeriodStatusRequest;
use App\Http\Requests\Admission\ConvertApplicationRequest;
use App\Http\Requests\Admission\CreateApplicationDraftRequest;
use App\Http\Requests\Admission\OpenApplicationPeriodRequest;
use App\Http\Requests\Admission\RegisterApplicationDocumentRequest;
use App\Http\Requests\Admission\TransitionApplicationStatusRequest;
use App\Http\Requests\Admission\UpdateApplicationPeriodRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
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
    ) {}

    public function index(Request $request, GetAdmissionWorkspaceHandler $handler): Response
    {
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

        $workspace = $handler->handle(new GetAdmissionWorkspaceQuery(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
        ));

        $this->securityAudit->record(
            SecurityEventType::AdmissionDataAccess,
            'admission.web.index',
            'allowed',
            $user,
            'admission',
            ['academic_year_id' => $academicYearId],
        );

        return Inertia::render('admission/index', [
            'workspace' => $workspace->toArray(),
            'filters' => [
                'academic_year_id' => $academicYearId,
            ],
            'authorization' => [
                'can_manage' => $user->can('manageAdmission'),
            ],
        ]);
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
            ->with('success', 'Application period opened.');
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
            ->with('success', 'Application period updated.');
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
            ->with('success', 'Application period status updated.');
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
            ->with('success', 'Application period archived.');
    }

    public function storeApplication(
        CreateApplicationDraftRequest $request,
        CreateApplicationDraftHandler $handler,
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
            gradeLevelId: (int) $request->validated('grade_level_id'),
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
            neighborhood: $request->validated('neighborhood'),
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

        return redirect()
            ->route('admission.index', [
                'academic_year_id' => $request->query('academic_year_id')
                    ?? $request->input('academic_year_id'),
            ])
            ->with('success', "Draft application {$result->applicationNumber} created.");
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

        return redirect()
            ->route('admission.index')
            ->with('success', 'Application status updated.');
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
            ->route('admission.index')
            ->with('success', "Converted to student #{$result->studentId}.");
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
            ->route('admission.index')
            ->with('success', 'Application document registered.');
    }
}
