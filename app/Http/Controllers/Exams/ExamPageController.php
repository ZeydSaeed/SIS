<?php

namespace App\Http\Controllers\Exams;

use App\Application\Exams\DTOs\ExamDTO;
use App\Application\Exams\DTOs\ExamSessionDTO;
use App\Application\Exams\Queries\GetExamHandler;
use App\Application\Exams\Queries\GetExamQuery;
use App\Application\Exams\Queries\ListExamSessionsHandler;
use App\Application\Exams\Queries\ListExamSessionsQuery;
use App\Application\Exams\Queries\ListExamsHandler;
use App\Application\Exams\Queries\ListExamsQuery;
use App\Domain\Exams\Exceptions\ExamNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\ExamRecord;
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

    public function index(Request $request, ListExamsHandler $handler): Response
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
            'filters' => [
                'session_status' => $sessionStatus,
            ],
        ]);
    }
}
