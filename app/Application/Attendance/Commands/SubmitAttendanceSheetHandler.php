<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Results\SubmitAttendanceSheetResult;
use App\Application\Attendance\Support\SheetGate;
use App\Application\Attendance\Support\SheetMarkWriter;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\Services\AttendanceSheetWorkflow;
use App\Domain\Attendance\ValueObjects\LessonWindow;
use App\Domain\Attendance\ValueObjects\SheetAction;

/**
 * Submit = final marks + completeness + Draft|Returned → Submitted, in one transaction. A double click either
 * replays the cached result (same key) or fails the version / transition guard — never a second submission.
 */
final class SubmitAttendanceSheetHandler implements CommandHandler
{
    public const COMMAND_NAME = 'SubmitAttendanceSheet';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly SheetGate $gate,
        private readonly SheetMarkWriter $writer,
        private readonly AttendanceSheetRepositoryInterface $sheets,
        private readonly OutboxRepository $outbox,
        private readonly AttendanceClockInterface $clock,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): SubmitAttendanceSheetResult
    {
        assert($command instanceof SubmitAttendanceSheetCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return SubmitAttendanceSheetResult::fromIdempotency($cached);
        }

        $data = $this->unitOfWork->transaction(function () use ($command): array {
            $context = $this->gate->forTeacherWork($command->sessionId, $command->schoolId, $command->academicYearId, $command->expectedVersion, $command->actor);
            $from = $context->sheet->sheet();
            $to = AttendanceSheetWorkflow::next($from, SheetAction::Submit);
            // Only the exceptions are marked (absent / late / excused / left early): the rest are present.
            $counts = $this->writer->write($context, $command->marks, true)['counts'];

            $now = $this->clock->now();
            $resubmission = $context->sheet->submissionCount > 0;
            $late = $context->window === LessonWindow::LateSubmission || $context->window === LessonWindow::Closed;
            if (! $this->sheets->transition($command->sessionId, $command->expectedVersion, $to, [
                'submitted_at' => $now->format(DATE_ATOM),
                'submitted_by' => $command->actor->userId,
                'submitted_late' => $late,
                'submission_count' => $context->sheet->submissionCount + 1,
            ])) {
                throw AttendanceRuleViolation::versionConflict($command->sessionId, $command->expectedVersion, -1);
            }
            $version = $command->expectedVersion + 1;
            $this->sheets->appendEvent($command->schoolId, $command->academicYearId, $command->sessionId, SheetAction::Submit->eventType($resubmission), $from->value, $to->value, $version, $command->actor->userId, $context->actorIsTeacher() ? null : 'on_behalf', $counts);
            $this->outbox->stage(new AttendanceWorkflowEvent(
                AttendanceWorkflowEvent::SHEET_SUBMITTED, $command->schoolId, $command->academicYearId, 'attendance_session', $command->sessionId, $command->actor->userId,
                ['sheet_status' => $from->value], ['sheet_status' => $to->value, 'counts' => $counts, 'late' => $late, 'resubmission' => $resubmission, 'teacher_id' => $context->sheet->teacherId],
                $now,
            ));

            return ['session_id' => $command->sessionId, 'version' => $version, 'sheet_status' => $to->value, 'counts' => $counts, 'submitted_at' => $now->format(DATE_ATOM), 'late' => $late];
        });

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return SubmitAttendanceSheetResult::success($data);
    }
}
