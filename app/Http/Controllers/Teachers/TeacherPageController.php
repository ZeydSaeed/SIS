<?php

namespace App\Http\Controllers\Teachers;

use App\Application\Curriculum\DTOs\SubjectDTO;
use App\Application\Curriculum\Queries\ListSubjectsHandler;
use App\Application\Curriculum\Queries\ListSubjectsQuery;
use App\Application\Shared\Results\ApplicationResult;
use App\Application\Teachers\Commands\AssignTeacherSubjectCommand;
use App\Application\Teachers\Commands\AssignTeacherSubjectHandler;
use App\Application\Teachers\Commands\DeactivateTeacherCommand;
use App\Application\Teachers\Commands\DeactivateTeacherHandler;
use App\Application\Teachers\Commands\ReactivateTeacherCommand;
use App\Application\Teachers\Commands\ReactivateTeacherHandler;
use App\Application\Teachers\Commands\RegisterTeacherCommand;
use App\Application\Teachers\Commands\RegisterTeacherHandler;
use App\Application\Teachers\Commands\UnlinkTeacherSubjectCommand;
use App\Application\Teachers\Commands\UnlinkTeacherSubjectHandler;
use App\Application\Teachers\Commands\UpdateTeacherCommand;
use App\Application\Teachers\Commands\UpdateTeacherHandler;
use App\Application\Teachers\DTOs\TeacherDTO;
use App\Application\Teachers\Queries\GetTeacherHandler;
use App\Application\Teachers\Queries\GetTeacherQuery;
use App\Application\Teachers\Queries\GetTeacherRosterHandler;
use App\Application\Teachers\Queries\GetTeacherRosterQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teachers\AssignTeacherSubjectRequest;
use App\Http\Requests\Teachers\DeactivateTeacherRequest;
use App\Http\Requests\Teachers\ReactivateTeacherRequest;
use App\Http\Requests\Teachers\RegisterTeacherRequest;
use App\Http\Requests\Teachers\UnlinkTeacherSubjectRequest;
use App\Http\Requests\Teachers\UpdateTeacherRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «المعلمون» page — the school's teaching staff for the year with the «تحرير» ribbon:
 * register / edit / deactivate / reactivate teachers and assign / unlink subjects.
 */
final class TeacherPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(
        Request $request,
        GetTeacherRosterHandler $roster,
        ListSubjectsHandler $subjects,
    ): Response {
        $user = $request->user();
        assert($user !== null);

        if (! $user->can('viewTeachers')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);

        $teachers = [];
        $total = 0;
        if ($academicYearId !== null) {
            $result = $roster->handle(new GetTeacherRosterQuery($schoolId, $academicYearId));
            $total = $result->total;
            $teachers = array_map(
                static fn (TeacherDTO $t): array => [
                    'id' => $t->id,
                    'employee_code' => $t->employeeCode,
                    'first_name' => $t->firstName,
                    'last_name' => $t->lastName,
                    'full_name' => $t->fullName,
                    'national_id' => $t->nationalId,
                    'specialization_field' => $t->specializationField,
                    'hire_date' => $t->hireDate,
                    'status' => $t->status,
                    'is_primary' => $t->isPrimary,
                    'subject_ids' => $result->subjectIdsByTeacher[$t->id] ?? [],
                ],
                $result->teachers,
            );
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.web.index',
            'viewed',
            $user,
            'school:'.$schoolId,
            ['academic_year_id' => $academicYearId, 'total' => $total],
        );

        return Inertia::render('teachers/index', [
            'teachers' => $teachers,
            'total' => $total,
            'subjects' => array_map(
                static fn (SubjectDTO $s): array => ['id' => $s->id, 'code' => $s->code, 'name' => $s->name],
                $subjects->handle(new ListSubjectsQuery),
            ),
            'filters' => ['academic_year_id' => $academicYearId],
            'authorization' => ['can_manage' => $user->can('manageTeachers')],
        ]);
    }

    public function show(Request $request, int $teacher, GetTeacherHandler $handler): Response
    {
        $user = $request->user();
        assert($user !== null);

        if (! $user->can('viewTeachers')) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        $schoolId = $this->schoolContext->requireId();
        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);

        $dto = $handler->handle(new GetTeacherQuery(
            schoolId: $schoolId,
            teacherId: $teacher,
            academicYearId: $academicYearId,
        ));

        if ($dto === null) {
            abort(404);
        }

        $this->securityAudit->record(
            SecurityEventType::TeacherDataAccess,
            'teachers.web.show',
            'viewed',
            $user,
            'teacher:'.$teacher,
            ['academic_year_id' => $academicYearId],
        );

        return Inertia::render('teachers/show', [
            'teacher' => [
                'id' => $dto->id,
                'employee_code' => $dto->employeeCode,
                'full_name' => $dto->fullName,
                'specialization_field' => $dto->specializationField,
                'status' => $dto->status,
                'is_primary' => $dto->isPrimary,
                'academic_year_id' => $dto->academicYearId,
                'hire_date' => $dto->hireDate,
            ],
            'filters' => [
                'academic_year_id' => $academicYearId,
            ],
        ]);
    }

    public function store(RegisterTeacherRequest $request, RegisterTeacherHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new RegisterTeacherCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            employeeCode: (string) $request->validated('employee_code'),
            firstName: (string) $request->validated('first_name'),
            lastName: (string) $request->validated('last_name'),
            nationalId: $request->validated('national_id'),
            specializationField: $request->validated('specialization_field'),
            hireDate: $request->validated('hire_date'),
            userId: null,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'teachers.web.register', 'created', 'teacher:'.($result->teacherId ?? 0), 'flash.teachers.registered');
    }

    public function update(int $teacher, UpdateTeacherRequest $request, UpdateTeacherHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new UpdateTeacherCommand(
            schoolId: $this->schoolContext->requireId(),
            teacherId: $teacher,
            firstName: (string) $request->validated('first_name'),
            lastName: (string) $request->validated('last_name'),
            nationalId: $request->validated('national_id'),
            specializationField: $request->validated('specialization_field'),
            hireDate: $request->validated('hire_date'),
            userId: null,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'teachers.web.update', 'updated', 'teacher:'.$teacher, 'flash.teachers.updated');
    }

    public function deactivate(int $teacher, DeactivateTeacherRequest $request, DeactivateTeacherHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new DeactivateTeacherCommand(
            schoolId: $this->schoolContext->requireId(),
            teacherId: $teacher,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'teachers.web.deactivate', 'deactivated', 'teacher:'.$teacher, 'flash.teachers.deactivated');
    }

    public function reactivate(int $teacher, ReactivateTeacherRequest $request, ReactivateTeacherHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new ReactivateTeacherCommand(
            schoolId: $this->schoolContext->requireId(),
            teacherId: $teacher,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'teachers.web.reactivate', 'reactivated', 'teacher:'.$teacher, 'flash.teachers.reactivated');
    }

    public function assignSubject(int $teacher, AssignTeacherSubjectRequest $request, AssignTeacherSubjectHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new AssignTeacherSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            teacherId: $teacher,
            subjectId: (int) $request->validated('subject_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        return $this->respond($request, $result, 'teachers.web.subject.assign', 'assigned', 'teacher:'.$teacher, 'flash.teachers.subjectAssigned');
    }

    public function unlinkSubject(int $teacher, UnlinkTeacherSubjectRequest $request, UnlinkTeacherSubjectHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new UnlinkTeacherSubjectCommand(
            schoolId: $this->schoolContext->requireId(),
            teacherId: $teacher,
            subjectId: (int) $request->validated('subject_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
        ));

        return $this->respond($request, $result, 'teachers.web.subject.unlink', 'unlinked', 'teacher:'.$teacher, 'flash.teachers.subjectUnlinked');
    }

    private function respond(
        Request $request,
        ApplicationResult $result,
        string $auditAction,
        string $outcome,
        string $resource,
        string $flash,
    ): RedirectResponse {
        if ($result->failed()) {
            return redirect()->back()->withErrors(['teacher' => $result->errors[0] ?? 'teachers.save_failed']);
        }

        $user = $request->user();
        assert($user !== null);
        $this->securityAudit->record(
            SecurityEventType::TeacherDataModified,
            $auditAction,
            $outcome,
            $user,
            $resource,
            ['from_idempotency' => $result->fromIdempotencyCache],
        );

        return redirect()->back()->with('success', $flash);
    }
}
