<?php

namespace App\Http\Controllers\Bank;

use App\Application\Bank\Commands\CreateQuestionCommand;
use App\Application\Bank\Commands\CreateQuestionHandler;
use App\Application\Bank\Commands\DecideQuestionVersionCommand;
use App\Application\Bank\Commands\DecideQuestionVersionHandler;
use App\Application\Bank\Commands\RetireQuestionCommand;
use App\Application\Bank\Commands\RetireQuestionHandler;
use App\Application\Bank\Commands\ReviseQuestionCommand;
use App\Application\Bank\Commands\ReviseQuestionHandler;
use App\Application\Bank\Commands\SubmitQuestionVersionCommand;
use App\Application\Bank\Commands\SubmitQuestionVersionHandler;
use App\Application\Bank\Commands\UpdateQuestionDraftCommand;
use App\Application\Bank\Commands\UpdateQuestionDraftHandler;
use App\Application\Bank\Contracts\QuestionAuthorScopePort;
use App\Application\Bank\Contracts\QuestionBankReadRepositoryInterface;
use App\Application\Bank\Queries\GetQuestionHandler;
use App\Application\Bank\Queries\ListQuestionsHandler;
use App\Application\Bank\Queries\ListQuestionsQuery;
use App\Domain\Bank\ValueObjects\QuestionType;
use App\Domain\Bank\ValueObjects\QuestionVersionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Bank\AuthorQuestionActionRequest;
use App\Http\Requests\Bank\DecideQuestionVersionRequest;
use App\Http\Requests\Bank\QuestionContentRules;
use App\Http\Requests\Bank\RetireQuestionRequest;
use App\Http\Requests\Bank\StoreQuestionRequest;
use App\Http\Requests\Bank\UpdateQuestionDraftRequest;
use App\Http\Support\AcademicYearContextResolver;
use App\Infrastructure\Persistence\Eloquent\BankQuestionRecord;
use App\Models\User;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «بنك الأسئلة» (EXAMIS 7.3a) — questions of a subject with versions: a teacher writes drafts of the subjects they
 * teach and sends them for review; an approver (never the author) approves or rejects; approving supersedes the
 * previous approved version; a question is retired, never deleted. The rules live in the handlers.
 */
final class QuestionBankController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly AcademicYearContextResolver $academicYears,
        private readonly QuestionAuthorScopePort $authorScope,
    ) {}

    public function index(Request $request, ListQuestionsHandler $handler, QuestionBankReadRepositoryInterface $read): Response
    {
        $this->authorize('viewAny', BankQuestionRecord::class);
        $user = $this->user($request);
        $schoolId = $this->schoolContext->requireId();
        $subjectIds = $this->scope($user, $request);
        $status = $request->query('status');
        $status = is_string($status) && (QuestionVersionStatus::tryFrom($status) !== null || $status === 'retired') ? $status : null;
        $filters = [
            'subject_id' => $request->filled('subject_id') ? (int) $request->query('subject_id') : null,
            'status' => $status,
            'search' => is_string($request->query('search')) ? mb_substr(trim((string) $request->query('search')), 0, 100) : null,
        ];

        $page = $handler->handle(new ListQuestionsQuery(
            schoolId: $schoolId,
            subjectIds: $subjectIds,
            subjectId: $filters['subject_id'],
            status: $filters['status'],
            search: $filters['search'],
            page: max(1, (int) $request->query('page', '1')),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataAccess, 'bank.web.index', 'allowed', $user, 'bank_questions', ['rows' => count($page['data'])]);

        return Inertia::render('bank/questions/index', [
            'filters' => $filters,
            'questions' => $page,
            'subjects' => $read->subjects($subjectIds),
            'types' => array_map(static fn (QuestionType $t): string => $t->value, QuestionType::cases()),
            'abilities' => $this->abilities($user),
            'timezone' => $this->displayTimezone(),
        ]);
    }

    public function show(Request $request, int $question, GetQuestionHandler $handler): Response
    {
        $this->authorize('viewAny', BankQuestionRecord::class);
        $user = $this->user($request);
        $abilities = $this->abilities($user);

        $data = $handler->handle($this->schoolContext->requireId(), $question, $this->scope($user, $request), $abilities['view_answer_key']);
        $this->securityAudit->record(SecurityEventType::GradeDataAccess, 'bank.web.show', 'allowed', $user, "bank_question:{$question}");

        return Inertia::render('bank/questions/show', [
            'question' => $data,
            'types' => array_map(static fn (QuestionType $t): string => $t->value, QuestionType::cases()),
            'abilities' => $abilities,
            'current_user_id' => (int) $user->id,
            'timezone' => $this->displayTimezone(),
        ]);
    }

    public function store(StoreQuestionRequest $request, CreateQuestionHandler $handler): RedirectResponse
    {
        $user = $this->user($request);
        $result = $handler->handle(new CreateQuestionCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $this->academicYears->resolve(null, $request),
            subjectId: (int) $request->validated('subject_id'),
            type: (string) $request->validated('type'),
            stem: (string) $request->validated('stem'),
            maxMark: (string) $request->validated('max_mark'),
            content: QuestionContentRules::content($request->validated()),
            actorId: (int) $user->id,
            schoolWide: $user->can('schoolWide', BankQuestionRecord::class),
            idempotencyKey: $request->idempotencyKey(),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'bank.web.store', 'created', $user, "bank_question:{$result->questionId}");

        return redirect()->route('bank.questions.show', ['question' => $result->questionId])->with('success', 'flash.bank.created');
    }

    public function update(UpdateQuestionDraftRequest $request, int $version, UpdateQuestionDraftHandler $handler): RedirectResponse
    {
        $user = $this->user($request);
        $result = $handler->handle(new UpdateQuestionDraftCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $this->academicYears->resolve(null, $request),
            versionId: $version,
            type: (string) $request->validated('type'),
            stem: (string) $request->validated('stem'),
            maxMark: (string) $request->validated('max_mark'),
            content: QuestionContentRules::content($request->validated()),
            actorId: (int) $user->id,
            schoolWide: $user->can('schoolWide', BankQuestionRecord::class),
            idempotencyKey: $request->idempotencyKey(),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'bank.web.update', 'updated', $user, "bank_question_version:{$version}");

        return redirect()->route('bank.questions.show', ['question' => $result->questionId])->with('success', 'flash.bank.saved');
    }

    public function submit(AuthorQuestionActionRequest $request, int $version, SubmitQuestionVersionHandler $handler): RedirectResponse
    {
        $user = $this->user($request);
        $result = $handler->handle(new SubmitQuestionVersionCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $this->academicYears->resolve(null, $request),
            versionId: $version,
            actorId: (int) $user->id,
            schoolWide: $user->can('schoolWide', BankQuestionRecord::class),
            idempotencyKey: $request->idempotencyKey(),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'bank.web.submit', 'submitted', $user, "bank_question_version:{$version}");

        return redirect()->route('bank.questions.show', ['question' => $result->questionId])->with('success', 'flash.bank.submitted');
    }

    public function decide(DecideQuestionVersionRequest $request, int $version, DecideQuestionVersionHandler $handler): RedirectResponse
    {
        $user = $this->user($request);
        $decision = (string) $request->validated('decision');
        $result = $handler->handle(new DecideQuestionVersionCommand(
            schoolId: $this->schoolContext->requireId(),
            versionId: $version,
            decision: $decision,
            note: (string) ($request->validated('note') ?? ''),
            actorId: (int) $user->id,
            idempotencyKey: $request->idempotencyKey(),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'bank.web.decide', $decision, $user, "bank_question_version:{$version}");

        return redirect()->route('bank.questions.show', ['question' => $result->questionId])
            ->with('success', $decision === 'approve' ? 'flash.bank.approved' : 'flash.bank.rejected');
    }

    public function revise(AuthorQuestionActionRequest $request, int $question, ReviseQuestionHandler $handler): RedirectResponse
    {
        $user = $this->user($request);
        $handler->handle(new ReviseQuestionCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $this->academicYears->resolve(null, $request),
            questionId: $question,
            actorId: (int) $user->id,
            schoolWide: $user->can('schoolWide', BankQuestionRecord::class),
            idempotencyKey: $request->idempotencyKey(),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'bank.web.revise', 'created', $user, "bank_question:{$question}");

        return redirect()->route('bank.questions.show', ['question' => $question])->with('success', 'flash.bank.revised');
    }

    public function retire(RetireQuestionRequest $request, int $question, RetireQuestionHandler $handler): RedirectResponse
    {
        $user = $this->user($request);
        $handler->handle(new RetireQuestionCommand(
            schoolId: $this->schoolContext->requireId(),
            questionId: $question,
            reason: (string) $request->validated('reason'),
            actorId: (int) $user->id,
            idempotencyKey: $request->idempotencyKey(),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'bank.web.retire', 'retired', $user, "bank_question:{$question}");

        return redirect()->route('bank.questions.show', ['question' => $question])->with('success', 'flash.bank.retired');
    }

    /**
     * Subjects the user sees: null = every subject (approvers), otherwise the subjects of their own teaching
     * assignments in the current academic year (none without a year or an assignment).
     *
     * @return list<int>|null
     */
    private function scope(User $user, Request $request): ?array
    {
        if ($user->can('schoolWide', BankQuestionRecord::class)) {
            return null;
        }
        $academicYearId = $this->academicYears->resolve(null, $request);

        return $academicYearId === null ? [] : $this->authorScope->subjectIdsForUser((int) $user->id, $this->schoolContext->requireId(), $academicYearId);
    }

    /** @return array{author: bool, approve: bool, view_answer_key: bool} */
    private function abilities(User $user): array
    {
        return [
            'author' => $user->can('author', BankQuestionRecord::class),
            'approve' => $user->can('approve', BankQuestionRecord::class),
            'view_answer_key' => $user->can('viewAnswerKey', BankQuestionRecord::class),
        ];
    }

    /**
     * Time zone the bank's instants are shown in (EXAMIS L-F7). `app.timezone` is UTC (storage); the local zone SIS
     * confirmed is `attendance.default_timezone` (Asia/Baghdad, 2026-10-12). An unknown value falls back to UTC.
     */
    private function displayTimezone(): string
    {
        $zone = (string) config('attendance.default_timezone', 'UTC');

        return in_array($zone, \DateTimeZone::listIdentifiers(), true) ? $zone : 'UTC';
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        assert($user instanceof User);

        return $user;
    }
}
