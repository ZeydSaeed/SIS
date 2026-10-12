<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Results\ReviewAttendanceSheetResult;
use App\Application\Attendance\Support\SheetGate;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Services\AttendanceSheetWorkflow;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Domain\Attendance\ValueObjects\SheetAction;

/**
 * Approve closes the session (no further marking — later changes only through the audited correction path);
 * return keeps it open for the teacher's correction; reject closes it and excludes it from statistics.
 * A second approval of the same sheet fails the transition guard.
 */
final class ReviewAttendanceSheetHandler implements CommandHandler
{
    public const COMMAND_NAME = 'ReviewAttendanceSheet';

    private const EVENT_NAMES = [
        'start_review' => AttendanceWorkflowEvent::SHEET_REVIEW_STARTED,
        'approve' => AttendanceWorkflowEvent::SHEET_APPROVED,
        'return' => AttendanceWorkflowEvent::SHEET_RETURNED,
        'reject' => AttendanceWorkflowEvent::SHEET_REJECTED,
    ];

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SheetGate $gate,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly OutboxRepository $outbox,
        private readonly AttendanceClockInterface $clock,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReviewAttendanceSheetResult
    {
        assert($command instanceof ReviewAttendanceSheetCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReviewAttendanceSheetResult::fromIdempotency($cached);
        }
        if ($command->action === SheetAction::Submit) {
            throw AttendanceRuleViolation::invalidTransition('review', $command->action->value);
        }
        $reason = AttendanceSheetWorkflow::assertReason($command->action, $command->reason);

        $data = $this->unitOfWork->transaction(function () use ($command, $reason): array {
            $sheet = $this->gate->lock($command->sessionId, $command->schoolId, $command->academicYearId, $command->expectedVersion);
            $from = $sheet->sheet();
            $to = AttendanceSheetWorkflow::next($from, $command->action);
            $now = $this->clock->now();

            $stamps = ['reviewed_at' => $now->format(DATE_ATOM), 'reviewed_by' => $command->actorUserId];
            if ($command->action !== SheetAction::StartReview) {
                $stamps['review_note'] = $reason;
            }
            if ($command->action === SheetAction::Approve || $command->action === SheetAction::Reject) {
                $stamps['status'] = SessionStatus::Closed->value;
            }
            if (! $this->sheets->transition($command->sessionId, $command->expectedVersion, $to, $stamps)) {
                throw AttendanceRuleViolation::versionConflict($command->sessionId, $command->expectedVersion, -1);
            }

            $version = $command->expectedVersion + 1;
            $this->sheets->appendEvent($command->schoolId, $command->academicYearId, $command->sessionId, $command->action->eventType(false), $from->value, $to->value, $version, $command->actorUserId, $reason, null);
            $this->outbox->stage(new AttendanceWorkflowEvent(
                self::EVENT_NAMES[$command->action->value], $command->schoolId, $command->academicYearId, 'attendance_session', $command->sessionId, $command->actorUserId,
                ['sheet_status' => $from->value], ['sheet_status' => $to->value, 'reason' => $reason, 'teacher_id' => $sheet->teacherId],
                $now,
            ));

            return ['session_id' => $command->sessionId, 'version' => $version, 'sheet_status' => $to->value, 'reviewed_at' => $now->format(DATE_ATOM)];
        });

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return ReviewAttendanceSheetResult::success($data);
    }
}
