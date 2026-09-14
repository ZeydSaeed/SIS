<?php

namespace App\Http\Controllers\Grades;

use App\Application\Exams\Commands\CorrectStudentGradeCommand;
use App\Application\Exams\Commands\CorrectStudentGradeHandler;
use App\Application\Exams\Commands\EnterStudentGradeCommand;
use App\Application\Exams\Commands\EnterStudentGradeHandler;
use App\Application\Exams\Commands\FinalizeStudentGradeCommand;
use App\Application\Exams\Commands\FinalizeStudentGradeHandler;
use App\Application\Exams\Commands\VoidStudentGradeCommand;
use App\Application\Exams\Commands\VoidStudentGradeHandler;
use App\Application\Exams\DTOs\StudentGradeDTO;
use App\Application\Exams\Queries\GetStudentGradeHandler;
use App\Application\Exams\Queries\GetStudentGradeQuery;
use App\Application\Exams\Queries\ListGradesForExamSessionHandler;
use App\Application\Exams\Queries\ListGradesForExamSessionQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CorrectStudentGradeRequest;
use App\Http\Requests\Exams\EnterStudentGradeRequest;
use App\Http\Requests\Exams\FinalizeStudentGradeRequest;
use App\Http\Requests\Exams\VoidStudentGradeRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GradesPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListGradesForExamSessionHandler $handler): Response
    {
        $this->authorize('viewAny', StudentGradeRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $sessionId = $request->filled('session_id') ? (int) $request->query('session_id') : null;

        $rows = [];
        if ($academicYearId !== null && $sessionId !== null && $sessionId > 0) {
            $items = $handler->handle(new ListGradesForExamSessionQuery(
                examSessionId: $sessionId,
                academicYearId: $academicYearId,
                schoolId: $schoolId,
            ));
            $rows = array_map(static fn (StudentGradeDTO $dto): array => $dto->toArray(), $items);
        }

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'grades.web.index',
            'allowed',
            $user,
            'student_grades',
            [
                'session_id' => $sessionId,
                'academic_year_id' => $academicYearId,
                'count' => count($rows),
            ],
        );

        return Inertia::render('grades/index', [
            'grades' => ['data' => $rows],
            'filters' => [
                'session_id' => $sessionId,
                'academic_year_id' => $academicYearId,
            ],
        ]);
    }

    public function enterForm(Request $request): Response
    {
        $this->authorize('create', StudentGradeRecord::class);

        return Inertia::render('grades/enter', [
            'defaults' => [
                'is_absent' => false,
            ],
        ]);
    }

    public function store(EnterStudentGradeRequest $request, EnterStudentGradeHandler $handler): RedirectResponse
    {
        $schoolId = $this->schoolContext->requireId();
        $score = $request->validated('score');

        $result = $handler->handle(new EnterStudentGradeCommand(
            schoolId: $schoolId,
            examEnrollmentId: (int) $request->validated('exam_enrollment_id'),
            score: $score !== null ? (string) $score : null,
            isAbsent: (bool) $request->validated('is_absent'),
            enteredBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'grades.web.store',
            'created',
            $request->user(),
            "grade:{$result->gradeId}",
            ['exam_enrollment_id' => (int) $request->validated('exam_enrollment_id')],
        );

        return redirect()
            ->route('grades.actions', [
                'grade_id' => $result->gradeId,
                'academic_year_id' => $result->academicYearId,
            ])
            ->with('success', 'Grade entered.');
    }

    public function actions(Request $request, GetStudentGradeHandler $handler): Response
    {
        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $gradeId = $request->filled('grade_id') ? (int) $request->query('grade_id') : null;

        $grade = null;
        if ($gradeId !== null && $gradeId > 0 && $academicYearId !== null) {
            $record = StudentGradeRecord::query()
                ->where('id', $gradeId)
                ->where('academic_year_id', $academicYearId)
                ->first();

            if ($record === null) {
                abort(404);
            }

            try {
                $this->authorize('view', $record);
            } catch (AuthorizationException) {
                $this->securityAudit->record(
                    SecurityEventType::IdorBlocked,
                    'grades.web.actions',
                    'denied',
                    $user,
                    "grade:{$gradeId}",
                );
                throw new AuthorizationException('This action is unauthorized.');
            }

            $dto = $handler->handle(new GetStudentGradeQuery($gradeId, $academicYearId, $schoolId));
            $grade = $dto->toArray();
        } else {
            $this->authorize('viewAny', StudentGradeRecord::class);
        }

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'grades.web.actions',
            'allowed',
            $user,
            $gradeId !== null ? "grade:{$gradeId}" : 'student_grades',
        );

        return Inertia::render('grades/actions', [
            'grade' => $grade,
            'filters' => [
                'grade_id' => $gradeId,
                'academic_year_id' => $academicYearId,
            ],
        ]);
    }

    public function correct(
        CorrectStudentGradeRequest $request,
        int $grade,
        CorrectStudentGradeHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();
        $score = $request->validated('score');

        $result = $handler->handle(new CorrectStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: $grade,
            academicYearId: (int) $request->validated('academic_year_id'),
            score: $score !== null ? (string) $score : null,
            isAbsent: (bool) $request->validated('is_absent'),
            reason: (string) $request->validated('reason'),
            correctedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'grades.web.correct',
            'corrected',
            $request->user(),
            "grade:{$result->newGradeId}",
            ['previous_grade_id' => $result->previousGradeId],
        );

        return redirect()
            ->route('grades.actions', [
                'grade_id' => $result->newGradeId,
                'academic_year_id' => $result->academicYearId,
            ])
            ->with('success', 'Grade corrected.');
    }

    public function void(
        VoidStudentGradeRequest $request,
        int $grade,
        VoidStudentGradeHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(new VoidStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: $grade,
            academicYearId: (int) $request->validated('academic_year_id'),
            reason: (string) $request->validated('reason'),
            voidedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'grades.web.void',
            'voided',
            $request->user(),
            "grade:{$result->gradeId}",
        );

        return redirect()
            ->route('grades.actions', [
                'grade_id' => $result->gradeId,
                'academic_year_id' => $result->academicYearId,
            ])
            ->with('success', 'Grade voided.');
    }

    public function finalize(
        FinalizeStudentGradeRequest $request,
        int $grade,
        FinalizeStudentGradeHandler $handler,
    ): RedirectResponse {
        $schoolId = $this->schoolContext->requireId();

        $result = $handler->handle(new FinalizeStudentGradeCommand(
            schoolId: $schoolId,
            gradeId: $grade,
            academicYearId: (int) $request->validated('academic_year_id'),
            finalizedBy: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataModified,
            'grades.web.finalize',
            'finalized',
            $request->user(),
            "grade:{$result->gradeId}",
        );

        return redirect()
            ->route('grades.actions', [
                'grade_id' => $result->gradeId,
                'academic_year_id' => $result->academicYearId,
            ])
            ->with('success', 'Grade finalized.');
    }
}
