<?php

namespace App\Http\Controllers\Enrollment;

use App\Application\Academic\DTOs\GradeLevelDTO;
use App\Application\Academic\Queries\ListGradeLevelsHandler;
use App\Application\Academic\Queries\ListGradeLevelsQuery;
use App\Application\Enrollment\Commands\CreateClassCommand;
use App\Application\Enrollment\Commands\CreateClassHandler;
use App\Application\Enrollment\Commands\CreateSectionCommand;
use App\Application\Enrollment\Commands\CreateSectionHandler;
use App\Application\Enrollment\Commands\DeactivateClassCommand;
use App\Application\Enrollment\Commands\DeactivateClassHandler;
use App\Application\Enrollment\Commands\DeactivateSectionCommand;
use App\Application\Enrollment\Commands\DeactivateSectionHandler;
use App\Application\Enrollment\Commands\ReactivateClassCommand;
use App\Application\Enrollment\Commands\ReactivateClassHandler;
use App\Application\Enrollment\Commands\ReactivateSectionCommand;
use App\Application\Enrollment\Commands\ReactivateSectionHandler;
use App\Application\Enrollment\Commands\UpdateClassCommand;
use App\Application\Enrollment\Commands\UpdateClassHandler;
use App\Application\Enrollment\Commands\UpdateSectionCommand;
use App\Application\Enrollment\Commands\UpdateSectionHandler;
use App\Application\Enrollment\DTOs\ClassDTO;
use App\Application\Enrollment\DTOs\SectionDTO;
use App\Application\Enrollment\Queries\GetClassStructureHandler;
use App\Application\Enrollment\Queries\GetClassStructureQuery;
use App\Application\Shared\Results\ApplicationResult;
use App\Application\Teachers\Queries\GetTeacherRosterHandler;
use App\Application\Teachers\Queries\GetTeacherRosterQuery;
use App\Domain\Teachers\ValueObjects\TeacherStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\DeactivateClassRequest;
use App\Http\Requests\Enrollment\DeactivateSectionRequest;
use App\Http\Requests\Enrollment\ReactivateClassRequest;
use App\Http\Requests\Enrollment\ReactivateSectionRequest;
use App\Http\Requests\Enrollment\SaveClassRequest;
use App\Http\Requests\Enrollment\SaveSectionRequest;
use App\Http\Support\AcademicYearContextResolver;
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

/**
 * «الصفوف والشعب» page — classes and sections of the school for a year, with capacity,
 * active enrollments and the section's homeroom teacher (رائد الصف). Delete = deactivate.
 */
final class ClassSectionPageController extends Controller
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
        GetClassStructureHandler $structure,
        ListGradeLevelsHandler $gradeLevels,
        GetTeacherRosterHandler $roster,
    ): Response {
        $user = $request->user();
        assert($user !== null);

        if (! $this->authorization->userHasPermission($user, Permission::ENROLLMENT_VIEW)
            || ! $this->schoolAccess->canAccessEnrollment($user)) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $requestedYear = $request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);

        $classes = [];
        $teachers = [];
        if ($academicYearId !== null) {
            $result = $structure->handle(new GetClassStructureQuery($schoolId, $academicYearId));
            $classes = array_map(static fn (ClassDTO $c): array => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'grade_level_id' => $c->gradeLevelId,
                'capacity' => $c->capacity,
                'status' => $c->status,
                'sections' => array_map(static fn (SectionDTO $s): array => [
                    'id' => $s->id,
                    'code' => $s->code,
                    'name' => $s->name,
                    'capacity' => $s->capacity,
                    'homeroom_teacher_id' => $s->homeroomTeacherId,
                    'status' => $s->status,
                    'enrolled' => $result->enrolledBySection[$s->id] ?? 0,
                ], $result->sectionsByClass[$c->id] ?? []),
            ], $result->classes);

            $teachers = array_map(
                static fn (array $t): array => [
                    'id' => $t['id'],
                    'full_name' => $t['full_name'],
                    'active' => $t['status'] === TeacherStatus::Active,
                ],
                $roster->handle(new GetTeacherRosterQuery($schoolId, $academicYearId))->teachers,
            );
        }

        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataAccess,
            'enrollment.web.structure.index',
            'viewed',
            $user,
            'school:'.$schoolId,
            ['academic_year_id' => $academicYearId, 'classes' => count($classes)],
        );

        return Inertia::render('organization/classes-sections', [
            'classes' => $classes,
            'gradeLevels' => array_map(
                static fn (GradeLevelDTO $g): array => ['id' => $g->id, 'name' => $g->name, 'level_order' => $g->levelOrder],
                $gradeLevels->handle(new ListGradeLevelsQuery),
            ),
            'teachers' => $teachers,
            'filters' => ['academic_year_id' => $academicYearId],
            'authorization' => [
                'can_manage' => $this->authorization->userHasPermission($user, Permission::ENROLLMENT_UPDATE),
            ],
        ]);
    }

    public function storeClass(SaveClassRequest $request, CreateClassHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreateClassCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            gradeLevelId: (int) $request->validated('grade_level_id'),
            name: (string) $request->validated('name'),
            capacity: $this->nullableInt($request->validated('capacity')),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'enrollment.web.class.store', 'class', 'flash.structure.classCreated');
    }

    public function updateClass(int $class, SaveClassRequest $request, UpdateClassHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new UpdateClassCommand(
            schoolId: $this->schoolContext->requireId(),
            classId: $class,
            gradeLevelId: (int) $request->validated('grade_level_id'),
            name: (string) $request->validated('name'),
            capacity: $this->nullableInt($request->validated('capacity')),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'enrollment.web.class.update', 'class', 'flash.structure.classUpdated');
    }

    public function deactivateClass(int $class, DeactivateClassRequest $request, DeactivateClassHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new DeactivateClassCommand(
            schoolId: $this->schoolContext->requireId(),
            classId: $class,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'enrollment.web.class.deactivate', 'class', 'flash.structure.classDeactivated', $class);
    }

    public function reactivateClass(int $class, ReactivateClassRequest $request, ReactivateClassHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new ReactivateClassCommand(
            schoolId: $this->schoolContext->requireId(),
            classId: $class,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'enrollment.web.class.reactivate', 'class', 'flash.structure.classReactivated', $class);
    }

    public function storeSection(SaveSectionRequest $request, CreateSectionHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreateSectionCommand(
            schoolId: $this->schoolContext->requireId(),
            classId: (int) $request->validated('class_id'),
            name: (string) $request->validated('name'),
            capacity: $this->nullableInt($request->validated('capacity')),
            homeroomTeacherId: $this->nullableInt($request->validated('homeroom_teacher_id')),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'enrollment.web.section.store', 'section', 'flash.structure.sectionCreated');
    }

    public function updateSection(int $section, SaveSectionRequest $request, UpdateSectionHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new UpdateSectionCommand(
            schoolId: $this->schoolContext->requireId(),
            sectionId: $section,
            name: (string) $request->validated('name'),
            capacity: $this->nullableInt($request->validated('capacity')),
            homeroomTeacherId: $this->nullableInt($request->validated('homeroom_teacher_id')),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'enrollment.web.section.update', 'section', 'flash.structure.sectionUpdated');
    }

    public function deactivateSection(int $section, DeactivateSectionRequest $request, DeactivateSectionHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new DeactivateSectionCommand(
            schoolId: $this->schoolContext->requireId(),
            sectionId: $section,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'enrollment.web.section.deactivate', 'section', 'flash.structure.sectionDeactivated', $section);
    }

    public function reactivateSection(int $section, ReactivateSectionRequest $request, ReactivateSectionHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new ReactivateSectionCommand(
            schoolId: $this->schoolContext->requireId(),
            sectionId: $section,
            idempotencyKey: (string) $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'enrollment.web.section.reactivate', 'section', 'flash.structure.sectionReactivated', $section);
    }

    private function respond(
        Request $request,
        ApplicationResult $result,
        string $auditAction,
        string $kind,
        string $flash,
        ?int $id = null,
    ): RedirectResponse {
        if ($result->failed()) {
            return redirect()->back()->withErrors([$kind => $result->errors[0] ?? 'enrollment.structure_failed']);
        }

        $user = $request->user();
        assert($user !== null);
        $this->securityAudit->record(
            SecurityEventType::EnrollmentDataModified,
            $auditAction,
            'success',
            $user,
            $kind.':'.($id ?? (property_exists($result, 'id') ? (int) $result->id : 0)),
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        $redirect = redirect()->back()->with('success', $flash);

        return $result->warnings === [] ? $redirect : $redirect->with('toast', ['type' => 'warning', 'message' => $result->warnings[0]]);
    }

    private function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
