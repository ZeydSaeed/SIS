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
use App\Application\Exams\Queries\GetExamSeatingContextHandler;
use App\Application\Exams\Queries\GetExamSeatingContextQuery;
use App\Application\Exams\Queries\ListExamSessionsHandler;
use App\Application\Exams\Queries\ListExamSessionsQuery;
use App\Application\Exams\Queries\ListExamsHandler;
use App\Application\Exams\Queries\ListExamsQuery;
use App\Domain\Exams\Exceptions\ExamNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
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
        GetExamSeatingContextHandler $seatingHandler,
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

        $canSeat = $user->can('createEnrollment', ExamRecord::class);
        $seating = $seatingHandler->handle(new GetExamSeatingContextQuery(
            schoolId: $schoolId,
            academicYearId: $dto->academicYearId,
            examSessionIds: array_map(static fn (ExamSessionDTO $session): int => $session->id, $sessions),
        ));

        return Inertia::render('exams/show', [
            'exam' => $dto->toArray(),
            'sessions' => [
                'data' => array_map(static fn (ExamSessionDTO $session): array => $session->toArray(), $sessions),
            ],
            'names' => $namesHandler->handle(new GetExamDisplayNamesQuery(
                termIds: [$dto->termId],
                academicYearIds: [$dto->academicYearId],
                subjectIds: array_map(static fn (ExamSessionDTO $session): int => $session->subjectId, $sessions),
            )),
            'admin' => $this->sessionForms($user, $schoolId, $dto->academicYearId, $lookupsHandler),
            'seating' => [
                'can_seat' => $canSeat,
                'classes' => $canSeat ? $seating['classes'] : [],
                'seat_counts' => $seating['seat_counts'],
                'result' => $request->session()->get('exam_seating_result'),
            ],
            'filters' => [
                'session_status' => $sessionStatus,
            ],
        ]);
    }

    /**
     * Create-exam form: shown only with exam.create and an academic year; choices are existing terms and exam types.
     *
     * @return array{can_create: bool, terms: list<array{id: int, name: string}>, exam_types: list<array{id: int, name: string, weight_percentage: int}>}
     */
    private function createExamForm(User $user, int $schoolId, ?int $academicYearId, GetExamFormLookupsHandler $lookups): array
    {
        $canCreate = $academicYearId !== null && $user->can('create', ExamRecord::class);
        $choices = $canCreate
            ? $lookups->handle(new GetExamFormLookupsQuery($schoolId, $academicYearId, forExam: true, forSession: false))
            : ['terms' => [], 'exam_types' => []];

        return ['can_create' => $canCreate, 'terms' => $choices['terms'], 'exam_types' => $choices['exam_types']];
    }

    /**
     * Session forms: create (exam.session.create, subjects of the school-year's active curricula), open and close.
     *
     * @return array{can_create_session: bool, can_open: bool, can_close: bool, subjects: list<array{id: int, name: string}>}
     */
    private function sessionForms(User $user, int $schoolId, int $academicYearId, GetExamFormLookupsHandler $lookups): array
    {
        $canCreateSession = $user->can('createSession', ExamRecord::class);

        return [
            'can_create_session' => $canCreateSession,
            'can_open' => $user->can('openSession', ExamRecord::class),
            'can_close' => $user->can('closeSession', ExamRecord::class),
            'subjects' => $canCreateSession
                ? $lookups->handle(new GetExamFormLookupsQuery($schoolId, $academicYearId, forExam: false, forSession: true))['subjects']
                : [],
        ];
    }
}
