<?php

namespace App\Http\Controllers\Exams;

use App\Application\Exams\Commands\CancelExamEnrollmentCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentHandler;
use App\Application\Exams\Commands\PresentExamEnrollmentCommand;
use App\Application\Exams\Commands\PresentExamEnrollmentHandler;
use App\Application\Exams\Commands\ReopenExamEnrollmentCommand;
use App\Application\Exams\Commands\ReopenExamEnrollmentHandler;
use App\Application\Exams\Commands\UpdateExamEnrollmentCommand;
use App\Application\Exams\Commands\UpdateExamEnrollmentHandler;
use App\Application\Exams\Queries\GetExamSessionRosterHandler;
use App\Application\Exams\Queries\GetExamSessionRosterQuery;
use App\Domain\Exams\Data\ExamEnrollmentSnapshot;
use App\Domain\Exams\Exceptions\ExamCancelBlockedException;
use App\Domain\Exams\Exceptions\ExamUpdateForbiddenException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\CancelExamEnrollmentRequest;
use App\Http\Requests\Exams\PresentExamEnrollmentRequest;
use App\Http\Requests\Exams\ReopenExamEnrollmentRequest;
use App\Http\Requests\Exams\UpdateExamEnrollmentRequest;
use App\Infrastructure\Persistence\Eloquent\ExamRecord;
use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «كشف المرشحين» — the seats of one exam session: confirm, present, absent, seat number, withdraw, reopen.
 * Same commands, requests and permissions as the API (exam.enrollment.update · present · cancel).
 */
final class ExamRosterController extends Controller
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
        private readonly ExamRepositoryInterface $exams,
    ) {}

    public function show(Request $request, int $exam, int $session, GetExamSessionRosterHandler $handler): Response
    {
        $this->authorize('viewAny', ExamRecord::class);
        $user = $request->user();
        assert($user !== null);

        $roster = $handler->handle(new GetExamSessionRosterQuery($this->schoolContext->requireId(), $exam, $session));
        abort_if($roster === null, 404);

        $this->securityAudit->record(SecurityEventType::GradeDataAccess, 'exams.web.roster', 'allowed', $user,
            "exam_session:{$session}", ['seat_count' => count($roster['seats'])]);

        return Inertia::render('exams/session', [
            'session' => $roster['session'],
            'seats' => ['data' => $roster['seats']],
            'can' => [
                'update' => $user->can('updateEnrollment', ExamRecord::class),
                'present' => $user->can('presentEnrollment', ExamRecord::class),
                'cancel' => $user->can('cancelEnrollment', ExamRecord::class),
                'reopen' => $user->can('reopenEnrollment', ExamRecord::class),
            ],
        ]);
    }

    /** Confirm (2) or absent (4) and/or the seat number — the request allows nothing else. */
    public function update(UpdateExamEnrollmentRequest $request, int $exam, int $session, int $seat, UpdateExamEnrollmentHandler $handler): RedirectResponse
    {
        $snapshot = $this->seatOf($exam, $session, $seat);
        $status = $request->validated('status');

        return $this->attempt(function () use ($request, $handler, $snapshot, $status): void {
            $handler->handle(new UpdateExamEnrollmentCommand(
                schoolId: $snapshot->schoolId,
                examEnrollmentId: $snapshot->id,
                examSessionId: $snapshot->examSessionId,
                enrollmentId: $snapshot->enrollmentId,
                actorUserId: (int) $request->user()?->id,
                idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
                status: $status !== null ? (int) $status : null,
                seatNumberProvided: $request->exists('seat_number'),
                seatNumber: $request->exists('seat_number') ? $this->seatNumber($request->validated('seat_number')) : null,
            ));
        }, $request, 'exams.web.roster.update', $seat, match ((int) $status) {
            2 => 'flash.exams.seatConfirmed',
            4 => 'flash.exams.seatAbsent',
            default => 'flash.exams.seatNumberSaved',
        });
    }

    public function present(PresentExamEnrollmentRequest $request, int $exam, int $session, int $seat, PresentExamEnrollmentHandler $handler): RedirectResponse
    {
        $snapshot = $this->seatOf($exam, $session, $seat);

        return $this->attempt(fn () => $handler->handle(new PresentExamEnrollmentCommand(
            schoolId: $snapshot->schoolId,
            examEnrollmentId: $snapshot->id,
            examSessionId: $snapshot->examSessionId,
            enrollmentId: $snapshot->enrollmentId,
            actorUserId: (int) $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        )), $request, 'exams.web.roster.present', $seat, 'flash.exams.seatPresent');
    }

    public function cancel(CancelExamEnrollmentRequest $request, int $exam, int $session, int $seat, CancelExamEnrollmentHandler $handler): RedirectResponse
    {
        $snapshot = $this->seatOf($exam, $session, $seat);

        return $this->attempt(fn () => $handler->handle(new CancelExamEnrollmentCommand(
            schoolId: $snapshot->schoolId,
            examEnrollmentId: $snapshot->id,
            examSessionId: $snapshot->examSessionId,
            enrollmentId: $snapshot->enrollmentId,
            actorUserId: (int) $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        )), $request, 'exams.web.roster.cancel', $seat, 'flash.exams.seatWithdrawn');
    }

    public function reopen(ReopenExamEnrollmentRequest $request, int $exam, int $session, int $seat, ReopenExamEnrollmentHandler $handler): RedirectResponse
    {
        $snapshot = $this->seatOf($exam, $session, $seat);
        $result = $handler->handle(new ReopenExamEnrollmentCommand(
            schoolId: $snapshot->schoolId,
            examEnrollmentId: $snapshot->id,
            actorUserId: (int) $request->user()?->id,
            idempotencyKey: trim((string) $request->header('X-Idempotency-Key')),
        ));

        if ($result->failed()) {
            return back()->with('error', 'exams.roster.action_refused');
        }
        $this->securityAudit->record(SecurityEventType::GradeDataModified, 'exams.web.roster.reopen', 'updated', $request->user(), "exam_enrollment:{$seat}");

        return back()->with('success', 'flash.exams.seatReopened');
    }

    /** The seat must be in the session, and the session in the exam, of the school in context. */
    private function seatOf(int $exam, int $session, int $seat): ExamEnrollmentSnapshot
    {
        $schoolId = $this->schoolContext->requireId();
        $snapshot = $this->exams->findExamEnrollmentByIdAndSchool($seat, $schoolId);
        $sessionSnapshot = $this->exams->findSessionByIdAndSchool($session, $schoolId);
        abort_if($snapshot === null || $snapshot->examSessionId !== $session || $sessionSnapshot?->examId !== $exam, 404);

        return $snapshot;
    }

    /** A blank seat number clears it. */
    private function seatNumber(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * Runs the command; a refusal by the seat's lifecycle comes back as one localized key (the domain messages are English prose).
     *
     * @param  callable(): mixed  $command
     */
    private function attempt(callable $command, Request $request, string $action, int $seat, string $success): RedirectResponse
    {
        try {
            $command();
        } catch (ExamValidationException|ExamUpdateForbiddenException|ExamCancelBlockedException) {
            return back()->with('error', 'exams.roster.action_refused');
        }
        $this->securityAudit->record(SecurityEventType::GradeDataModified, $action, 'updated', $request->user(), "exam_enrollment:{$seat}");

        return back()->with('success', $success);
    }
}
