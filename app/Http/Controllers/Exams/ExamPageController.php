<?php

namespace App\Http\Controllers\Exams;

use App\Application\Exams\DTOs\ExamDTO;
use App\Application\Exams\DTOs\ExamSessionDTO;
use App\Application\Exams\Queries\GetExamDisplayNamesHandler;
use App\Application\Exams\Queries\GetExamDisplayNamesQuery;
use App\Application\Exams\Queries\GetExamFormLookupsHandler;
use App\Application\Exams\Queries\GetExamFormLookupsQuery;
use App\Application\Exams\Queries\GetExamHandler;
use App\Application\Exams\Queries\GetExamQuery;
use App\Application\Exams\Queries\ListExamSessionsHandler;
use App\Application\Exams\Queries\ListExamSessionsQuery;
use App\Application\Exams\Queries\ListExamsHandler;
use App\Application\Exams\Queries\ListExamsQuery;
use App\Domain\Exams\Exceptions\ExamNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\BankQuestionRecord;
use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class ExamPageController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListExamsHandler $handler, GetExamDisplayNamesHandler $namesHandler, GetExamFormLookupsHandler $lookupsHandler): Response
    {
        $this->authorize('viewAny', ExamRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $requestedYear = $request->filled('academic_year_id')
            ? (int) $request->query('academic_year_id')
            : null;
        $academicYearId = $this->academicYears->resolve($requestedYear);
        $status = $request->filled('status') ? (int) $request->query('status') : null;

        $items = $handler->handle(new ListExamsQuery(
            schoolId: $schoolId,
            academicYearId: $academicYearId,
            status: $status,
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'exams.web.index',
            'allowed',
            $user,
            'exams',
            ['academic_year_id' => $academicYearId, 'count' => count($items)],
        );

        return Inertia::render('exams/index', [
            'exams' => [
                'data' => array_map(static fn (ExamDTO $dto): array => $dto->toArray(), $items),
            ],
            // EXAMIS 7.2: display names instead of raw ids, and the create form only for exam.create.
            'names' => $namesHandler->handle(new GetExamDisplayNamesQuery(
                termIds: array_map(static fn (ExamDTO $dto): int => $dto->termId, $items),
            )),
            'admin' => $this->createExamForm($user, $schoolId, $academicYearId, $lookupsHandler),
            'filters' => [
                'academic_year_id' => $academicYearId,
                'status' => $status,
            ],
        ]);
    }

    public function show(
        Request $request,
        int $exam,
        GetExamHandler $examHandler,
        ListExamSessionsHandler $sessionsHandler,
        GetExamDisplayNamesHandler $namesHandler,
        GetExamFormLookupsHandler $lookupsHandler,
    ): Response {
        $this->authorize('viewAny', ExamRecord::class);

        $schoolId = $this->schoolContext->requireId();
        $user = $request->user();
        assert($user !== null);

        $dto = $examHandler->handle(new GetExamQuery(
            schoolId: $schoolId,
            examId: $exam,
        ));

        if ($dto === null) {
            throw ExamNotFoundException::forId($exam);
        }

        $sessionStatus = $request->filled('session_status')
            ? (int) $request->query('session_status')
            : null;

        $sessions = $sessionsHandler->handle(new ListExamSessionsQuery(
            schoolId: $schoolId,
            examId: $exam,
            status: $sessionStatus,
        ));

        $this->securityAudit->record(
            SecurityEventType::GradeDataAccess,
            'exams.web.show',
            'allowed',
            $user,
            "exam:{$exam}",
            ['session_count' => count($sessions)],
        );

        return Inertia::render('exams/show', [
            'exam' => $dto->toArray(),
            'sessions' => [
                'data' => array_map(static fn (ExamSessionDTO $session): array => $session->toArray(), $sessions),
            ],
            // EXAMIS 7.2: names of the year, term and session subjects; the forms only for the permissions held.
            'names' => $namesHandler->handle(new GetExamDisplayNamesQuery(
                termIds: [$dto->termId],
                academicYearIds: [$dto->academicYearId],
                subjectIds: array_map(static fn (ExamSessionDTO $session): int => $session->subjectId, $sessions),
            )),
            'admin' => $this->sessionForms($user, $schoolId, $dto->academicYearId, $lookupsHandler),
            'filters' => [
                'session_status' => $sessionStatus,
            ],
        ]);
    }

    /**
     * Create-exam form: only with exam.create and an academic year; choices are the year's terms and the school's types.
     *
     * `can_view_bank` (EXAMIS 7.3a): the ribbon links «بنك الأسئلة» only for users who may open it.
     *
     * @return array{can_create: bool, terms: list<array{id: int, name: string}>, exam_types: list<array<string, mixed>>, can_view_bank: bool}
     */
    private function createExamForm(User $user, int $schoolId, ?int $academicYearId, GetExamFormLookupsHandler $lookups): array
    {
        $canCreate = $academicYearId !== null && $user->can('create', ExamRecord::class);
        $choices = $canCreate
            ? $lookups->handle(new GetExamFormLookupsQuery($schoolId, $academicYearId, forExam: true, forSession: false))
            : ['terms' => [], 'exam_types' => []];

        return [
            'can_create' => $canCreate,
            'terms' => $choices['terms'],
            'exam_types' => $choices['exam_types'],
            'can_view_bank' => $user->can('viewAny', BankQuestionRecord::class),
        ];
    }

    /**
     * Exam and session forms: edit / cancel the exam (exam.update · exam.cancel), create sessions (subjects of the
     * school-year), edit / cancel (exam.session.update), open and close (exam.session.open · exam.session.close).
     *
     * @return array<string, mixed>
     */
    private function sessionForms(User $user, int $schoolId, int $academicYearId, GetExamFormLookupsHandler $lookups): array
    {
        $canCreateSession = $user->can('createSession', ExamRecord::class);
        $canUpdate = $user->can('update', ExamRecord::class);
        $choices = $canCreateSession || $canUpdate
            ? $lookups->handle(new GetExamFormLookupsQuery($schoolId, $academicYearId, forExam: $canUpdate, forSession: $canCreateSession))
            : [];

        return [
            'can_create_session' => $canCreateSession,
            'can_update' => $canUpdate,
            'can_cancel' => $user->can('cancel', ExamRecord::class),
            'can_update_session' => $user->can('updateSession', ExamRecord::class),
            'can_open' => $user->can('openSession', ExamRecord::class),
            'can_close' => $user->can('closeSession', ExamRecord::class),
            'subjects' => $choices['subjects'] ?? [],
            'terms' => $choices['terms'] ?? [],
            'exam_types' => $choices['exam_types'] ?? [],
        ];
    }
}
