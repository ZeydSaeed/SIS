<?php

namespace App\Http\Controllers\Exams;

use App\Application\Exams\Commands\CloseExamSessionCommand;
use App\Application\Exams\Commands\CloseExamSessionHandler;
use App\Application\Exams\Commands\CreateExamCommand;
use App\Application\Exams\Commands\CreateExamHandler;
use App\Application\Exams\Commands\CreateExamSessionCommand;
use App\Application\Exams\Commands\CreateExamSessionHandler;
use App\Application\Exams\Commands\OpenExamSessionCommand;
use App\Application\Exams\Commands\OpenExamSessionHandler;
use App\Application\Exams\Queries\GetExamSessionHandler;
use App\Application\Exams\Queries\GetExamSessionQuery;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CloseExamSessionRequest;
use App\Http\Requests\Exams\CreateExamRequest;
use App\Http\Requests\Exams\CreateExamSessionRequest;
use App\Http\Requests\Exams\OpenExamSessionRequest;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;

/**
 * Web forms of exam administration — the same commands, requests and permissions as the API
 * (exam.create · exam.session.create · exam.session.open · exam.session.close).
 */
final class ExamAdministrationController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function store(CreateExamRequest $request, CreateExamHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreateExamCommand(
            schoolId: $this->schoolContext->requireId(),
            academicYearId: (int) $request->validated('academic_year_id'),
            termId: (int) $request->validated('term_id'),
            examTypeId: (int) $request->validated('exam_type_id'),
            name: (string) $request->validated('name'),
            startDate: (string) $request->validated('start_date'),
            endDate: (string) $request->validated('end_date'),
            actorUserId: (int) $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'exams.web.store', 'created', $request->user(), "exam:{$result->examId}");

        return redirect()->route('exams.show', ['exam' => $result->examId])->with('success', 'flash.exams.created');
    }

    public function storeSession(CreateExamSessionRequest $request, int $exam, CreateExamSessionHandler $handler): RedirectResponse
    {
        $result = $handler->handle(new CreateExamSessionCommand(
            schoolId: $this->schoolContext->requireId(),
            examId: $exam,
            subjectId: (int) $request->validated('subject_id'),
            sessionDate: (string) $request->validated('session_date'),
            startTime: (string) $request->validated('start_time'),
            endTime: (string) $request->validated('end_time'),
            actorUserId: (int) $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
            maxGrade: (int) ($request->validated('max_grade') ?? 100),
            passGrade: (int) ($request->validated('pass_grade') ?? 50),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'exams.web.sessions.store', 'created', $request->user(), "exam_session:{$result->examSessionId}");

        return redirect()->route('exams.show', ['exam' => $exam])->with('success', 'flash.exams.sessionCreated');
    }

    public function openSession(OpenExamSessionRequest $request, int $exam, int $session, OpenExamSessionHandler $handler, GetExamSessionHandler $sessions): RedirectResponse
    {
        $this->assertSessionOfExam($exam, $session, $sessions);
        $handler->handle(new OpenExamSessionCommand(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $session,
            actorUserId: (int) $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'exams.web.sessions.open', 'updated', $request->user(), "exam_session:{$session}");

        return redirect()->route('exams.show', ['exam' => $exam])->with('success', 'flash.exams.sessionOpened');
    }

    public function closeSession(CloseExamSessionRequest $request, int $exam, int $session, CloseExamSessionHandler $handler, GetExamSessionHandler $sessions): RedirectResponse
    {
        $this->assertSessionOfExam($exam, $session, $sessions);
        $handler->handle(new CloseExamSessionCommand(
            schoolId: $this->schoolContext->requireId(),
            examSessionId: $session,
            actorUserId: (int) $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'exams.web.sessions.close', 'updated', $request->user(), "exam_session:{$session}");

        return redirect()->route('exams.show', ['exam' => $exam])->with('success', 'flash.exams.sessionClosed');
    }

    /** The session in the URL must belong to the exam in the URL (and to the school in context). */
    private function assertSessionOfExam(int $exam, int $session, GetExamSessionHandler $sessions): void
    {
        $dto = $sessions->handle(new GetExamSessionQuery($this->schoolContext->requireId(), $session));
        abort_if($dto === null || $dto->examId !== $exam, 404);
    }
}
