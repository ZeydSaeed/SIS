<?php

namespace App\Http\Controllers\Curriculum;

use App\Application\Curriculum\Commands\AddSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\AddSubjectPrerequisiteHandler;
use App\Application\Curriculum\Commands\CreateCurriculumCommand;
use App\Application\Curriculum\Commands\CreateCurriculumHandler;
use App\Application\Curriculum\Commands\CreateSubjectCommand;
use App\Application\Curriculum\Commands\CreateSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateCurriculumCommand;
use App\Application\Curriculum\Commands\DeactivateCurriculumHandler;
use App\Application\Curriculum\Commands\DeactivateCurriculumSubjectCommand;
use App\Application\Curriculum\Commands\DeactivateCurriculumSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateSubjectCommand;
use App\Application\Curriculum\Commands\DeactivateSubjectHandler;
use App\Application\Curriculum\Commands\DeactivateSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\DeactivateSubjectPrerequisiteHandler;
use App\Application\Curriculum\Commands\LinkCurriculumSubjectCommand;
use App\Application\Curriculum\Commands\LinkCurriculumSubjectHandler;
use App\Application\Curriculum\Commands\ReactivateCurriculumCommand;
use App\Application\Curriculum\Commands\ReactivateCurriculumHandler;
use App\Application\Curriculum\Commands\ReactivateCurriculumSubjectCommand;
use App\Application\Curriculum\Commands\ReactivateCurriculumSubjectHandler;
use App\Application\Curriculum\Commands\ReactivateSubjectCommand;
use App\Application\Curriculum\Commands\ReactivateSubjectHandler;
use App\Application\Curriculum\Commands\ReactivateSubjectPrerequisiteCommand;
use App\Application\Curriculum\Commands\ReactivateSubjectPrerequisiteHandler;
use App\Application\Curriculum\Commands\UpdateCurriculumCommand;
use App\Application\Curriculum\Commands\UpdateCurriculumHandler;
use App\Application\Curriculum\Commands\UpdateSubjectCommand;
use App\Application\Curriculum\Commands\UpdateSubjectHandler;
use App\Application\Curriculum\DTOs\CurriculumDTO;
use App\Application\Curriculum\DTOs\CurriculumSubjectDTO;
use App\Application\Curriculum\DTOs\SubjectDTO;
use App\Application\Curriculum\Queries\GetCurriculumHandler;
use App\Application\Curriculum\Queries\GetCurriculumQuery;
use App\Application\Curriculum\Queries\ListCurriculaHandler;
use App\Application\Curriculum\Queries\ListCurriculaQuery;
use App\Application\Curriculum\Queries\ListCurriculumSubjectsHandler;
use App\Application\Curriculum\Queries\ListCurriculumSubjectsQuery;
use App\Application\Curriculum\Queries\ListSubjectPrerequisitesHandler;
use App\Application\Curriculum\Queries\ListSubjectPrerequisitesQuery;
use App\Application\Curriculum\Queries\ListSubjectsHandler;
use App\Application\Curriculum\Queries\ListSubjectsQuery;
use App\Application\Enrollment\Commands\AssignEnrollmentSubjectCommand;
use App\Application\Enrollment\Commands\AssignEnrollmentSubjectHandler;
use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Enrollment\Queries\ListEnrollmentSubjectsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentSubjectsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Curriculum\AddSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\CreateCurriculumRequest;
use App\Http\Requests\Curriculum\CreateSubjectRequest;
use App\Http\Requests\Curriculum\DeactivateCurriculumRequest;
use App\Http\Requests\Curriculum\DeactivateCurriculumSubjectRequest;
use App\Http\Requests\Curriculum\DeactivateSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\DeactivateSubjectRequest;
use App\Http\Requests\Curriculum\LinkCurriculumSubjectRequest;
use App\Http\Requests\Curriculum\ReactivateCurriculumRequest;
use App\Http\Requests\Curriculum\ReactivateCurriculumSubjectRequest;
use App\Http\Requests\Curriculum\ReactivateSubjectPrerequisiteRequest;
use App\Http\Requests\Curriculum\ReactivateSubjectRequest;
use App\Http\Requests\Curriculum\UpdateCurriculumRequest;
use App\Http\Requests\Curriculum\UpdateSubjectRequest;
use App\Http\Requests\Enrollment\AssignEnrollmentSubjectRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class CurriculumPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(
        Request $request,
        ListCurriculaHandler $curriculaHandler,
        ListSubjectsHandler $subjectsHandler,
        ListCurriculumSubjectsHandler $linksHandler,
        EnrollmentReadRepositoryInterface $enrollmentReads,
    ): Response {
        $user = $request->user();
        assert($user !== null);

        if (! $user->can('viewCurriculum')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $allCurricula = $request->boolean('all_curricula');
        $requestedYear = (! $allCurricula && $request->filled('academic_year_id'))
            ? (int) $request->query('academic_year_id')
            : null;
        // Filter option catalogs still need a year context; curricula list may span all years.
        $academicYearId = $this->academicYears->resolve($allCurricula ? null : $requestedYear);
        $selectedCurriculumId = $request->filled('curriculum_id')
            ? (int) $request->query('curriculum_id')
            : null;

        $q = trim((string) $request->query('q', ''));
        $status = $request->filled('status') ? (int) $request->query('status') : null;
        $classId = null;
        if (! $allCurricula && $request->exists('class_id')) {
            if ($request->filled('class_id')) {
                $classId = (int) $request->query('class_id');
            }
        }
        $gradeLevelId = (! $allCurricula && $request->filled('grade_level_id'))
            ? (int) $request->query('grade_level_id')
            : null;
        $specializationId = (! $allCurricula && $request->filled('specialization_id'))
            ? (int) $request->query('specialization_id')
            : null;
        $branchId = (! $allCurricula && $request->filled('branch_id'))
            ? (int) $request->query('branch_id')
            : null;
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(max(1, (int) $request->query('per_page', $allCurricula ? 100 : 17)), 100);

        $subjectQ = trim((string) $request->query('subject_q', ''));
        $subjectStatus = $request->filled('subject_status') ? (int) $request->query('subject_status') : null;
        $subjectType = $request->filled('subject_type') ? (int) $request->query('subject_type') : null;
        $subjectPage = max(1, (int) $request->query('subject_page', 1));
        $subjectPerPage = min(max(1, (int) $request->query('subject_per_page', 17)), 100);

        $filterOptions = $enrollmentReads->listFilterOptions($schoolId, $academicYearId);

        $branch = $allCurricula ? '' : trim((string) $request->query('branch', ''));
        $specialization = $allCurricula ? '' : trim((string) $request->query('specialization', ''));

        // صف SSOT = enrollment.classes (الأول / الثاني / الثالث) → grade_level_id for curricula filter.
        if (! $allCurricula && $classId !== null) {
            $gradeLevelId = null;
            foreach ($filterOptions['classes'] ?? [] as $classRow) {
                if ((int) ($classRow['id'] ?? 0) === $classId) {
                    $gradeLevelId = (int) ($classRow['grade_level_id'] ?? 0) ?: null;
                    break;
                }
            }
        }
        $meta = $this->buildLabelMaps($filterOptions);

        $curriculaPayload = ['data' => [], 'pagination' => ['page' => 1, 'per_page' => $perPage, 'total' => 0, 'last_page' => 1]];
        if ($allCurricula || $academicYearId !== null) {
            $result = $curriculaHandler->handle(new ListCurriculaQuery(
                schoolId: $schoolId,
                academicYearId: $allCurricula ? null : $academicYearId,
                includeInactive: true,
                status: $status,
                gradeLevelId: $gradeLevelId,
                specializationId: $specializationId,
                branchId: $branchId,
                q: $q,
                page: $page,
                perPage: $perPage,
                paginate: true,
            ));
            assert(isset($result['items']));
            $curriculaPayload = [
                'data' => array_map(
                    fn (CurriculumDTO $dto): array => $this->mapCurriculumRow($dto, $meta),
                    $result['items'],
                ),
                'pagination' => [
                    'page' => $result['page'],
                    'per_page' => $result['per_page'],
                    'total' => $result['total'],
                    'last_page' => $result['last_page'],
                ],
            ];
        }

        // Subjects tab merges the full client catalog with DB rows (status/grades).
        // Inactive subjects sort after active ones — a small page would omit them and
        // the UI would fall back to catalog default status=1. Always load the full set.
        $subjectsForCatalog = $subjectsHandler->handle(new ListSubjectsQuery(
            includeInactive: true,
            status: $subjectStatus,
            subjectType: $subjectType,
            q: $subjectQ,
            page: 1,
            perPage: 500,
            paginate: true,
        ));
        assert(isset($subjectsForCatalog['items']));
        $mapSubjectRow = static fn (SubjectDTO $dto): array => [
            'id' => $dto->id,
            'code' => $dto->code,
            'name' => $dto->name,
            'name_en' => $dto->nameEn,
            'subject_type' => $dto->subjectType,
            'credit_hours' => $dto->creditHours,
            'max_grade' => $dto->maxGrade,
            'pass_grade' => $dto->passGrade,
            'status' => $dto->status,
            'prerequisites' => $dto->prerequisitesText ?? '',
        ];
        $subjectsPayload = [
            'data' => array_map($mapSubjectRow, $subjectsForCatalog['items']),
            'pagination' => [
                'page' => $subjectsForCatalog['page'],
                'per_page' => $subjectsForCatalog['per_page'],
                'total' => $subjectsForCatalog['total'],
                'last_page' => $subjectsForCatalog['last_page'],
            ],
        ];

        $subjectById = [];
        foreach ($subjectsPayload['data'] as $subject) {
            $subjectById[$subject['id']] = $subject;
        }
        // Ensure every subject (incl. inactive) is available for linked-row names.
        $allSubjects = $subjectsHandler->handle(new ListSubjectsQuery(includeInactive: true));
        assert(is_array($allSubjects) && ! isset($allSubjects['items']));
        foreach ($allSubjects as $dto) {
            assert($dto instanceof SubjectDTO);
            $subjectById[$dto->id] = $mapSubjectRow($dto);
        }

        $linkedSubjects = [];
        if ($selectedCurriculumId !== null && $academicYearId !== null) {
            $links = $linksHandler->handle(new ListCurriculumSubjectsQuery(
                schoolId: $schoolId,
                curriculumId: $selectedCurriculumId,
                includeInactive: true,
            ));
            if ($links !== null) {
                $linkedSubjects = array_map(
                    static function (CurriculumSubjectDTO $dto) use ($subjectById): array {
                        $subject = $subjectById[$dto->subjectId] ?? null;

                        return [
                            'id' => $dto->id,
                            'curriculum_id' => $dto->curriculumId,
                            'subject_id' => $dto->subjectId,
                            'subject_code' => $subject['code'] ?? null,
                            'subject_name' => $subject['name'] ?? null,
                            'weekly_hours' => $dto->weeklyHours,
                            'is_required' => $dto->isRequired,
                            'subject_order' => $dto->subjectOrder,
                            'status' => $dto->status,
                        ];
                    },
                    $links,
                );
            }
        }

        // Attach nested subject rows for each curriculum on the current page (list UI).
        $subjectsByName = [];
        foreach ($subjectById as $subject) {
            $name = (string) ($subject['name'] ?? '');
            if ($name !== '' && ! isset($subjectsByName[$name])) {
                $subjectsByName[$name] = $subject;
            }
        }
        foreach ($curriculaPayload['data'] as $index => $curriculumRow) {
            $curriculaPayload['data'][$index]['subjects'] = $this->mapCurriculumNestedSubjects(
                schoolId: $schoolId,
                curriculumId: (int) $curriculumRow['id'],
                branchName: $curriculumRow['branch_name'] ?? null,
                departmentName: $curriculumRow['department_name'] ?? null,
                subjectById: $subjectById,
                subjectsByName: $subjectsByName,
                linksHandler: $linksHandler,
            );
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataAccess,
            'curriculum.web.index',
            'listed',
            $user,
            'school:'.$schoolId,
            [
                'curricula_count' => $curriculaPayload['pagination']['total'],
                'subjects_count' => $subjectsPayload['pagination']['total'],
                'academic_year_id' => $academicYearId,
                'selected_curriculum_id' => $selectedCurriculumId,
            ],
        );

        return Inertia::render('curriculum/index', [
            'curricula' => $curriculaPayload,
            'subjects' => $subjectsPayload,
            'linkedSubjects' => $linkedSubjects,
            'filters' => [
                'academic_year_id' => $allCurricula ? null : $academicYearId,
                'all_curricula' => $allCurricula,
                'curriculum_id' => $selectedCurriculumId,
                'q' => $q,
                'status' => $status,
                'class_id' => $classId,
                'grade_level_id' => $gradeLevelId,
                'specialization_id' => $specializationId,
                'branch_id' => $branchId,
                'branch' => $branch,
                'specialization' => $specialization,
                'page' => $page,
                'per_page' => $perPage,
                'subject_q' => $subjectQ,
                'subject_status' => $subjectStatus,
                'subject_type' => $subjectType,
                'subject_page' => $subjectPage,
                'subject_per_page' => $subjectPerPage,
            ],
            'filterOptions' => [
                'classes' => $filterOptions['classes'] ?? [],
                'branches' => $filterOptions['branches'] ?? [],
                'departments' => $filterOptions['departments'] ?? [],
                'specializations' => $filterOptions['specializations'] ?? [],
            ],
            'authorization' => [
                'canView' => true,
                'canManage' => $user->can('manageCurriculum'),
            ],
        ]);
    }

    public function storeCurriculum(
        CreateCurriculumRequest $request,
        CreateCurriculumHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $subjectIds = $request->validated('subject_ids') ?? [];
        $result = $handler->handle(new CreateCurriculumCommand(
            schoolId: $schoolId,
            academicYearId: (int) $request->validated('academic_year_id'),
            gradeLevelId: (int) $request->validated('grade_level_id'),
            name: (string) $request->validated('name'),
            specializationId: $request->validated('specialization_id') !== null
                ? (int) $request->validated('specialization_id')
                : null,
            idempotencyKey: $this->idempotencyKey($request),
            subjectIds: array_map(static fn (mixed $id): int => (int) $id, is_array($subjectIds) ? $subjectIds : []),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'curriculum' => $result->errors[0] ?? 'curriculum.curriculum_create_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.curricula.store',
            'created',
            $request->user(),
            'curriculum:'.$result->curriculumId,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return redirect()->back()->with('success', 'flash.curriculum.created');
    }

    public function updateCurriculum(
        int $curriculum,
        UpdateCurriculumRequest $request,
        UpdateCurriculumHandler $handler,
    ): RedirectResponse {
        $fields = [];
        if ($request->exists('name')) {
            $fields['name'] = (string) $request->validated('name');
        }
        if ($request->exists('specialization_id')) {
            $fields['specialization_id'] = $request->validated('specialization_id') !== null
                ? (int) $request->validated('specialization_id')
                : null;
        }

        $result = $handler->handle(new UpdateCurriculumCommand(
            schoolId: $this->schoolContext->requireId(),
            curriculumId: $curriculum,
            fields: $fields,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'curriculum' => $result->errors[0] ?? 'curriculum.curriculum_update_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.curricula.update',
            'updated',
            $request->user(),
            'curriculum:'.$result->curriculumId,
            ['fields' => $result->fields],
        );

        return redirect()->back()->with('success', 'flash.curriculum.updated');
    }

    public function deactivateCurriculum(
        int $curriculum,
        DeactivateCurriculumRequest $request,
        DeactivateCurriculumHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new DeactivateCurriculumCommand(
            schoolId: $this->schoolContext->requireId(),
            curriculumId: $curriculum,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'curriculum' => $result->errors[0] ?? 'curriculum.curriculum_deactivate_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.curricula.deactivate',
            'deactivated',
            $request->user(),
            'curriculum:'.$result->curriculumId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.deactivated');
    }

    public function reactivateCurriculum(
        int $curriculum,
        ReactivateCurriculumRequest $request,
        ReactivateCurriculumHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new ReactivateCurriculumCommand(
            schoolId: $this->schoolContext->requireId(),
            curriculumId: $curriculum,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'curriculum' => $result->errors[0] ?? 'curriculum.curriculum_reactivate_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.curricula.reactivate',
            'reactivated',
            $request->user(),
            'curriculum:'.$result->curriculumId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.reactivated');
    }

    public function storeSubject(
        CreateSubjectRequest $request,
        CreateSubjectHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new CreateSubjectCommand(
            code: (string) $request->validated('code'),
            name: (string) $request->validated('name'),
            nameEn: $request->validated('name_en'),
            subjectType: (int) $request->validated('subject_type'),
            creditHours: $request->validated('credit_hours') !== null
                ? (int) $request->validated('credit_hours')
                : null,
            maxGrade: (int) ($request->validated('max_grade') ?? 100),
            passGrade: (int) ($request->validated('pass_grade') ?? 50),
            idempotencyKey: $this->idempotencyKey($request),
            prerequisitesText: $request->validated('prerequisites_text'),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'subject' => $result->errors[0] ?? 'curriculum.subject_create_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.subjects.store',
            'created',
            $request->user(),
            'subject:'.$result->subjectId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.subjectCreated');
    }

    public function updateSubject(
        int $subject,
        UpdateSubjectRequest $request,
        UpdateSubjectHandler $handler,
    ): RedirectResponse {
        $fields = [];
        foreach (['name', 'name_en', 'subject_type', 'credit_hours', 'max_grade', 'pass_grade', 'prerequisites_text'] as $key) {
            if ($request->exists($key)) {
                $fields[$key] = $request->validated($key);
            }
        }

        $result = $handler->handle(new UpdateSubjectCommand(
            subjectId: $subject,
            fields: $fields,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'subject' => $result->errors[0] ?? 'curriculum.subject_update_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.subjects.update',
            'updated',
            $request->user(),
            'subject:'.$result->subjectId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.subjectUpdated');
    }

    public function deactivateSubject(
        int $subject,
        DeactivateSubjectRequest $request,
        DeactivateSubjectHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new DeactivateSubjectCommand(
            subjectId: $subject,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'subject' => $result->errors[0] ?? 'curriculum.subject_deactivate_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.subjects.deactivate',
            'deactivated',
            $request->user(),
            'subject:'.$result->subjectId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.subjectDeactivated');
    }

    public function reactivateSubject(
        int $subject,
        ReactivateSubjectRequest $request,
        ReactivateSubjectHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new ReactivateSubjectCommand(
            subjectId: $subject,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'subject' => $result->errors[0] ?? 'curriculum.subject_reactivate_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.subjects.reactivate',
            'reactivated',
            $request->user(),
            'subject:'.$result->subjectId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.subjectReactivated');
    }

    public function storeCurriculumSubject(
        int $curriculum,
        LinkCurriculumSubjectRequest $request,
        LinkCurriculumSubjectHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new LinkCurriculumSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            curriculumId: $curriculum,
            subjectId: (int) $request->validated('subject_id'),
            weeklyHours: $request->validated('weekly_hours') !== null
                ? (int) $request->validated('weekly_hours')
                : null,
            isRequired: (bool) ($request->validated('is_required') ?? true),
            subjectOrder: (int) ($request->validated('subject_order') ?? 0),
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'link' => $result->errors[0] ?? 'curriculum.curriculum_subject_link_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.curriculum_subjects.store',
            'linked',
            $request->user(),
            'curriculum_subject:'.$result->linkId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.subjectLinked');
    }

    public function deactivateCurriculumSubject(
        int $link,
        DeactivateCurriculumSubjectRequest $request,
        DeactivateCurriculumSubjectHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new DeactivateCurriculumSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            linkId: $link,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'link' => $result->errors[0] ?? 'curriculum.curriculum_subject_deactivate_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.curriculum_subjects.deactivate',
            'deactivated',
            $request->user(),
            'curriculum_subject:'.$result->linkId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.subjectLinkDeactivated');
    }

    public function reactivateCurriculumSubject(
        int $link,
        ReactivateCurriculumSubjectRequest $request,
        ReactivateCurriculumSubjectHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new ReactivateCurriculumSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            linkId: $link,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'link' => $result->errors[0] ?? 'curriculum.curriculum_subject_reactivate_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.curriculum_subjects.reactivate',
            'reactivated',
            $request->user(),
            'curriculum_subject:'.$result->linkId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.subjectLinkReactivated');
    }

    public function show(
        Request $request,
        int $curriculum,
        GetCurriculumHandler $getCurriculum,
        ListCurriculumSubjectsHandler $linksHandler,
        ListSubjectsHandler $subjectsHandler,
        ListSubjectPrerequisitesHandler $prereqHandler,
        EnrollmentReadRepositoryInterface $enrollmentReads,
        ListEnrollmentSubjectsHandler $enrollmentSubjectsHandler,
    ): Response {
        $user = $request->user();
        assert($user !== null);
        if (! $user->can('viewCurriculum')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $dto = $getCurriculum->handle(new GetCurriculumQuery($schoolId, $curriculum));
        if ($dto === null) {
            abort(404);
        }

        $filterOptions = $enrollmentReads->listFilterOptions($schoolId, $dto->academicYearId);
        $meta = $this->buildLabelMaps($filterOptions);
        $plan = $this->mapCurriculumRow($dto, $meta);

        $activeSubjects = $subjectsHandler->handle(new ListSubjectsQuery(includeInactive: false));
        assert(is_array($activeSubjects) && ! isset($activeSubjects['items']));
        $subjectById = [];
        foreach ($activeSubjects as $subject) {
            assert($subject instanceof SubjectDTO);
            $subjectById[$subject->id] = [
                'id' => $subject->id,
                'code' => $subject->code,
                'name' => $subject->name,
                'subject_type' => $subject->subjectType,
                'credit_hours' => $subject->creditHours,
                'max_grade' => $subject->maxGrade,
                'pass_grade' => $subject->passGrade,
                'status' => $subject->status,
            ];
        }

        $links = $linksHandler->handle(new ListCurriculumSubjectsQuery($schoolId, $curriculum, true)) ?? [];
        $linkedSubjects = [];
        $prerequisites = [];
        foreach ($links as $link) {
            $subject = $subjectById[$link->subjectId] ?? null;
            $linkedSubjects[] = [
                'id' => $link->id,
                'curriculum_id' => $link->curriculumId,
                'subject_id' => $link->subjectId,
                'subject_code' => $subject['code'] ?? null,
                'subject_name' => $subject['name'] ?? null,
                'weekly_hours' => $link->weeklyHours,
                'is_required' => $link->isRequired,
                'subject_order' => $link->subjectOrder,
                'status' => $link->status,
            ];

            foreach ($prereqHandler->handle(new ListSubjectPrerequisitesQuery($link->subjectId, true)) as $prereq) {
                $prerequisites[] = [
                    'id' => $prereq->id,
                    'subject_id' => $prereq->subjectId,
                    'subject_name' => $subjectById[$prereq->subjectId]['name'] ?? null,
                    'prerequisite_subject_id' => $prereq->prerequisiteSubjectId,
                    'prerequisite_subject_name' => $subjectById[$prereq->prerequisiteSubjectId]['name'] ?? null,
                    'status' => $prereq->status,
                ];
            }
        }

        $departmentId = $plan['department_id'] ?? null;
        $enrollmentsResult = $enrollmentReads->paginate(
            schoolId: $schoolId,
            academicYearId: $dto->academicYearId,
            page: max(1, (int) $request->query('enrollment_page', 1)),
            perPage: min(max(1, (int) $request->query('enrollment_per_page', 17)), 50),
            status: 1,
            q: trim((string) $request->query('enrollment_q', '')),
            specializationId: $dto->specializationId,
            branchId: $plan['branch_id'] ?? null,
            departmentId: is_int($departmentId) ? $departmentId : null,
        );

        $selectedEnrollmentId = $request->filled('enrollment_id')
            ? (int) $request->query('enrollment_id')
            : null;
        $enrollmentSubjects = [];
        if ($selectedEnrollmentId !== null) {
            $assigned = $enrollmentSubjectsHandler->handle(new ListEnrollmentSubjectsQuery(
                schoolId: $schoolId,
                enrollmentId: $selectedEnrollmentId,
            ));
            if ($assigned !== null) {
                $enrollmentSubjects = array_map(
                    static function ($row) use ($subjectById): array {
                        $subject = $subjectById[$row->subjectId] ?? null;

                        return [
                            'id' => $row->id,
                            'enrollment_id' => $row->enrollmentId,
                            'subject_id' => $row->subjectId,
                            'subject_code' => $subject['code'] ?? null,
                            'subject_name' => $subject['name'] ?? null,
                            'is_elective' => $row->isElective,
                            'status' => $row->status,
                        ];
                    },
                    $assigned,
                );
            }
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataAccess,
            'curriculum.web.show',
            'viewed',
            $user,
            'curriculum:'.$curriculum,
            [],
        );

        return Inertia::render('curriculum/show', [
            'curriculum' => $plan,
            'linkedSubjects' => $linkedSubjects,
            'prerequisites' => $prerequisites,
            'subjectOptions' => array_values($subjectById),
            'enrollments' => [
                'data' => array_map(static fn ($e): array => $e->toArray(), $enrollmentsResult['items']),
                'pagination' => $enrollmentsResult['pagination'],
            ],
            'selectedEnrollmentId' => $selectedEnrollmentId,
            'enrollmentSubjects' => $enrollmentSubjects,
            'filterOptions' => [
                'classes' => $filterOptions['classes'] ?? [],
                'branches' => $filterOptions['branches'] ?? [],
                'departments' => $filterOptions['departments'] ?? [],
                'specializations' => $filterOptions['specializations'] ?? [],
            ],
            'filters' => [
                'enrollment_q' => trim((string) $request->query('enrollment_q', '')),
                'enrollment_page' => (int) $request->query('enrollment_page', 1),
                'tab' => (string) $request->query('tab', 'subjects'),
            ],
            'authorization' => [
                'canView' => true,
                'canManage' => $user->can('manageCurriculum'),
                'canAssignEnrollmentSubjects' => $user->can('update', \App\Infrastructure\Persistence\Eloquent\EnrollmentRecord::class),
            ],
        ]);
    }

    public function storePrerequisite(
        int $subject,
        AddSubjectPrerequisiteRequest $request,
        AddSubjectPrerequisiteHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new AddSubjectPrerequisiteCommand(
            subjectId: $subject,
            prerequisiteSubjectId: (int) $request->validated('prerequisite_subject_id'),
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'prerequisite' => $result->errors[0] ?? 'curriculum.prerequisite_create_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.prerequisites.store',
            'created',
            $request->user(),
            'prerequisite:'.$result->prerequisiteId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.prerequisiteCreated');
    }

    public function deactivatePrerequisite(
        int $prerequisite,
        DeactivateSubjectPrerequisiteRequest $request,
        DeactivateSubjectPrerequisiteHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new DeactivateSubjectPrerequisiteCommand(
            prerequisiteId: $prerequisite,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'prerequisite' => $result->errors[0] ?? 'curriculum.prerequisite_deactivate_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.prerequisites.deactivate',
            'deactivated',
            $request->user(),
            'prerequisite:'.$result->prerequisiteId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.prerequisiteDeactivated');
    }

    public function reactivatePrerequisite(
        int $prerequisite,
        ReactivateSubjectPrerequisiteRequest $request,
        ReactivateSubjectPrerequisiteHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new ReactivateSubjectPrerequisiteCommand(
            prerequisiteId: $prerequisite,
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'prerequisite' => $result->errors[0] ?? 'curriculum.prerequisite_reactivate_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::CurriculumDataModified,
            'curriculum.web.prerequisites.reactivate',
            'reactivated',
            $request->user(),
            'prerequisite:'.$result->prerequisiteId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.prerequisiteReactivated');
    }

    public function assignEnrollmentSubject(
        int $enrollment,
        AssignEnrollmentSubjectRequest $request,
        AssignEnrollmentSubjectHandler $handler,
    ): RedirectResponse {
        $result = $handler->handle(new AssignEnrollmentSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            enrollmentId: $enrollment,
            subjectId: (int) $request->validated('subject_id'),
            isElective: (bool) ($request->validated('is_elective') ?? false),
            idempotencyKey: $this->idempotencyKey($request),
        ));

        if ($result->failed()) {
            return redirect()->back()->withErrors([
                'enrollment_subject' => $result->errors[0] ?? 'enrollment.subject_assign_failed',
            ]);
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            'curriculum.web.enrollment_subjects.store',
            'assigned',
            $request->user(),
            'enrollment_subject:'.$result->linkId,
        );

        return redirect()->back()->with('success', 'flash.curriculum.enrollmentSubjectAssigned');
    }

    /**
     * @param  array<string, mixed>  $filterOptions
     * @return array{gradeNames: array<int, string>, specNames: array<int, string>, specMeta: array<int, array{department_id: ?int, name: string}>, departmentMeta: array<int, array{branch_id: ?int, name: string}>, branchNames: array<int, string>}
     */
    private function buildLabelMaps(array $filterOptions): array
    {
        $gradeNames = [];
        foreach ($filterOptions['grade_levels'] ?? [] as $grade) {
            $gradeNames[(int) $grade['id']] = (string) $grade['name'];
        }
        $specNames = [];
        $specMeta = [];
        foreach ($filterOptions['specializations'] ?? [] as $spec) {
            $id = (int) $spec['id'];
            $specNames[$id] = (string) $spec['name'];
            $specMeta[$id] = [
                'department_id' => $spec['department_id'] !== null ? (int) $spec['department_id'] : null,
                'name' => (string) $spec['name'],
            ];
        }
        $departmentMeta = [];
        foreach ($filterOptions['departments'] ?? [] as $dept) {
            $departmentMeta[(int) $dept['id']] = [
                'branch_id' => $dept['branch_id'] !== null ? (int) $dept['branch_id'] : null,
                'name' => (string) $dept['name'],
            ];
        }
        $branchNames = [];
        foreach ($filterOptions['branches'] ?? [] as $branch) {
            $branchNames[(int) $branch['id']] = (string) $branch['name'];
        }

        return compact('gradeNames', 'specNames', 'specMeta', 'departmentMeta', 'branchNames');
    }

    /**
     * @param  array<int, array<string, mixed>>  $subjectById
     * @param  array<string, array<string, mixed>>  $subjectsByName
     * @return list<array{name: string, subject_type: int, credit_hours: int|null, max_grade: int|null}>
     */
    private function mapCurriculumNestedSubjects(
        int $schoolId,
        int $curriculumId,
        ?string $branchName,
        ?string $departmentName,
        array $subjectById,
        array $subjectsByName,
        ListCurriculumSubjectsHandler $linksHandler,
    ): array {
        $links = $linksHandler->handle(new ListCurriculumSubjectsQuery(
            schoolId: $schoolId,
            curriculumId: $curriculumId,
            includeInactive: false,
        )) ?? [];

        if ($links !== []) {
            $rows = [];
            foreach ($links as $link) {
                $subject = $subjectById[$link->subjectId] ?? null;
                $rows[] = [
                    'name' => (string) ($subject['name'] ?? ('#'.$link->subjectId)),
                    'subject_type' => (int) ($subject['subject_type'] ?? 1),
                    'credit_hours' => $link->weeklyHours ?? ($subject['credit_hours'] ?? null),
                    'max_grade' => isset($subject['max_grade']) ? (int) $subject['max_grade'] : null,
                ];
            }

            return $rows;
        }

        if ($branchName === null || $branchName === '') {
            return [];
        }

        $catalog = \Database\Seeders\Support\CurriculumSubjectCatalogReference::subjectsFor(
            $branchName,
            $departmentName ?? '',
        );

        $rows = [];
        foreach ($catalog as $item) {
            $matched = $subjectsByName[$item['name']] ?? null;
            $rows[] = [
                'name' => $item['name'],
                'subject_type' => (int) $item['subject_type'],
                'credit_hours' => (int) $item['credit_hours'],
                'max_grade' => isset($matched['max_grade']) ? (int) $matched['max_grade'] : 100,
            ];
        }

        return $rows;
    }

    /**
     * @param  array{gradeNames: array<int, string>, specNames: array<int, string>, specMeta: array<int, array{department_id: ?int, name: string}>, departmentMeta: array<int, array{branch_id: ?int, name: string}>, branchNames: array<int, string>}  $meta
     * @return array<string, mixed>
     */
    private function mapCurriculumRow(CurriculumDTO $dto, array $meta): array
    {
        $specId = $dto->specializationId;
        $departmentId = $specId !== null ? ($meta['specMeta'][$specId]['department_id'] ?? null) : null;
        $branchId = $departmentId !== null ? ($meta['departmentMeta'][$departmentId]['branch_id'] ?? null) : null;

        return [
            'id' => $dto->id,
            'name' => $dto->name,
            'grade_level_id' => $dto->gradeLevelId,
            'grade_level_name' => $meta['gradeNames'][$dto->gradeLevelId] ?? null,
            'specialization_id' => $specId,
            'specialization_name' => $specId !== null ? ($meta['specNames'][$specId] ?? null) : null,
            'department_id' => $departmentId,
            'department_name' => $departmentId !== null
                ? ($meta['departmentMeta'][$departmentId]['name'] ?? null)
                : null,
            'branch_id' => $branchId,
            'branch_name' => $branchId !== null ? ($meta['branchNames'][$branchId] ?? null) : null,
            'status' => $dto->status,
            'academic_year_id' => $dto->academicYearId,
            'school_id' => $dto->schoolId,
            'subjects' => [],
        ];
    }

    private function idempotencyKey(Request $request): string
    {
        $header = $request->header('X-Idempotency-Key');
        if (is_string($header) && trim($header) !== '') {
            return trim($header);
        }

        return (string) Str::uuid();
    }
}
