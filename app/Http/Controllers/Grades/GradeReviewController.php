<?php

namespace App\Http\Controllers\Grades;

use App\Application\Exams\Commands\DecideGradeReviewCommand;
use App\Application\Exams\Commands\DecideGradeReviewHandler;
use App\Application\Exams\Commands\OpenGradeReviewCommand;
use App\Application\Exams\Commands\OpenGradeReviewHandler;
use App\Application\Exams\Commands\StartGradeReviewCommand;
use App\Application\Exams\Commands\StartGradeReviewHandler;
use App\Application\Exams\Commands\WithdrawGradeReviewCommand;
use App\Application\Exams\Commands\WithdrawGradeReviewHandler;
use App\Application\Exams\Queries\ListGradeReviewsHandler;
use App\Application\Exams\Queries\ListGradeReviewsQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\DecideGradeReviewRequest;
use App\Http\Requests\Exams\OpenGradeReviewRequest;
use App\Http\Requests\Exams\StartGradeReviewRequest;
use App\Http\Requests\Exams\WithdrawGradeReviewRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\StudentGradeRecord;
use App\Intelligence\Support\CorrelationContext;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «مراجعات الدرجات» — objections to official grades: open, take, decide (accept = official correction) or withdraw. */
final class GradeReviewController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
    ) {}

    public function index(Request $request, ListGradeReviewsHandler $handler): Response
    {
        $this->authorize('viewAny', StudentGradeRecord::class);
        $user = $request->user();
        assert($user !== null);
        $yearId = $this->academicYears->resolve($request->filled('academic_year_id') ? (int) $request->query('academic_year_id') : null);
        $status = in_array($request->query('status'), ['submitted', 'under_review', 'accepted', 'rejected', 'withdrawn'], true) ? (string) $request->query('status') : null;

        $rows = $yearId === null ? [] : $handler->handle(new ListGradeReviewsQuery($this->schoolContext->requireId(), $yearId, $status));
        $this->securityAudit->record(SecurityEventType::GradeDataAccess, 'grades.web.reviews', 'allowed', $user, 'grade_reviews', ['rows' => count($rows)]);

        return Inertia::render('grades/reviews', [
            'rows' => $rows,
            'filters' => ['academic_year_id' => $yearId, 'status' => $status],
            'abilities' => ['request' => $user->can('requestReview', StudentGradeRecord::class), 'decide' => $user->can('decideReview', StudentGradeRecord::class)],
            'userId' => $user->id,
        ]);
    }

    public function store(OpenGradeReviewRequest $request, OpenGradeReviewHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new OpenGradeReviewCommand(
            schoolId: $this->schoolContext->requireId(),
            gradeId: (int) $request->validated('grade_id'),
            academicYearId: (int) $request->validated('academic_year_id'),
            reason: (string) $request->validated('reason'),
            requestedBy: $request->user()?->id,
            correlationId: CorrelationContext::id(),
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'grades.web.review.open', 'opened', $request->user(), 'grade_review:'.$result->reviewId, []);

        return redirect()->route('grades.reviews', ['academic_year_id' => $request->validated('academic_year_id')])->with('success', 'flash.grades.review.opened');
    }

    public function start(StartGradeReviewRequest $request, int $review, StartGradeReviewHandler $handler): RedirectResponse
    {
        $handler->handle(new StartGradeReviewCommand($this->schoolContext->requireId(), $review, $request->user()?->id, $request->header('X-Idempotency-Key')));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'grades.web.review.start', 'started', $request->user(), 'grade_review:'.$review, []);

        return redirect()->back()->with('success', 'flash.grades.review.started');
    }

    public function decide(DecideGradeReviewRequest $request, int $review, DecideGradeReviewHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new DecideGradeReviewCommand(
            schoolId: $this->schoolContext->requireId(),
            reviewId: $review,
            decision: (string) $request->validated('decision'),
            note: (string) $request->validated('note'),
            newScore: $request->validated('score') !== null ? (string) $request->validated('score') : null,
            isAbsent: (bool) $request->validated('is_absent'),
            reviewerId: $request->user()?->id,
            idempotencyKey: $request->header('X-Idempotency-Key'),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'grades.web.review.decide', (string) $request->validated('decision'), $request->user(),
            'grade_review:'.$review, ['resulting_grade_id' => $result->resultingGradeId]);

        return redirect()->back()->with('success', 'flash.grades.review.'.$request->validated('decision'));
    }

    public function withdraw(WithdrawGradeReviewRequest $request, int $review, WithdrawGradeReviewHandler $handler): RedirectResponse
    {
        $handler->handle(new WithdrawGradeReviewCommand($this->schoolContext->requireId(), $review, $request->user()?->id, $request->header('X-Idempotency-Key')));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'grades.web.review.withdraw', 'withdrawn', $request->user(), 'grade_review:'.$review, []);

        return redirect()->back()->with('success', 'flash.grades.review.withdrawn');
    }
}
