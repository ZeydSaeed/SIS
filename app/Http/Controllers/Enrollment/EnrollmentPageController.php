<?php

namespace App\Http\Controllers\Enrollment;

use App\Application\Enrollment\Commands\EnrollStudentCommand;
use App\Application\Enrollment\Commands\EnrollStudentHandler;
use App\Application\Enrollment\Commands\UpdateEnrollmentPlacementCommand;
use App\Application\Enrollment\Commands\UpdateEnrollmentPlacementHandler;
use App\Application\Enrollment\Queries\GetEnrollmentHandler;
use App\Application\Enrollment\Queries\GetEnrollmentQuery;
use App\Application\Enrollment\Queries\ListEnrollmentsHandler;
use App\Application\Enrollment\Queries\ListEnrollmentsQuery;
use App\Application\Student\Queries\GetStudentHandler;
use App\Application\Student\Queries\GetStudentQuery;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Student\Exceptions\StudentNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Enrollment\EnrollStudentRequest;
use App\Http\Requests\Enrollment\UpdateEnrollmentPlacementRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
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
    ) {}

    public function index(Request $request, ListEnrollmentsHandler $handler): Response
    {
        $this->authorize('viewAny', EnrollmentRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
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
            'enrollments.web.index',
            'allowed',
            $user,
            'enrollments',
            ['page' => $page, 'per_page' => $perPage, 'academic_year_id' => $academicYearId],
        );

        return Inertia::render('enrollments/index', [
            'enrollments' => $result->toArray(),
            'filters' => [
                'academic_year_id' => $academicYearId,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
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

    public function create(Request $request, GetStudentHandler $students): Response
    {
        $this->authorize('create', EnrollmentRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $studentId = $request->filled('student_id') ? (int) $request->query('student_id') : null;

        if ($studentId !== null) {
            try {
                $student = $students->handle(new GetStudentQuery($studentId, $schoolId, $academicYearId));
                if ($student->academicYearId !== null) {
                    $academicYearId = $student->academicYearId;
                }
            } catch (StudentNotFoundException) {
                // Keep the resolved year when the student is unknown for this school.
            }
        }

        return Inertia::render('enrollments/create', [
            'defaults' => [
                'academic_year_id' => $academicYearId,
                'student_id' => $studentId,
                'effective_from' => now()->toDateString(),
            ],
        ]);
    }

    public function store(EnrollStudentRequest $request, EnrollStudentHandler $handler): RedirectResponse
    {
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
            'enrollments.web.store',
            'created',
            $request->user(),
            "enrollment:{$result->enrollmentId}",
        );

        return redirect()
            ->route('enrollments.show', ['enrollment' => $result->enrollmentId])
            ->with('success', 'Enrollment created.');
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

        return redirect()
            ->route('enrollments.show', ['enrollment' => $enrollment])
            ->with('success', 'Enrollment placement updated.');
    }
}
