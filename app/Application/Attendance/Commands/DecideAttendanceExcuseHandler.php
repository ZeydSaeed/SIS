<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Results\DecideAttendanceExcuseResult;
use App\Application\Attendance\Support\ExcuseApplication;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Events\AttendanceWorkflowEvent;
use App\Domain\Attendance\Repositories\AttendanceExcuseRepositoryInterface;
use App\Domain\Attendance\Services\ExcuseDecisionGuard;
use App\Domain\Attendance\ValueObjects\ExcuseStatus;

/**
 * Approval is an official correction: the student's «غائب» rows in the excuse range become «غياب بعذر», every
 * touched sheet gets a «تصحيح» history entry and a new version, and the previous statuses go to the audit trail.
 */
final class DecideAttendanceExcuseHandler implements CommandHandler
{
    public const COMMAND_NAME = 'DecideAttendanceExcuse';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AttendanceExcuseRepositoryInterface $excuses,
        private readonly ExcuseApplication $application,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): DecideAttendanceExcuseResult
    {
        assert($command instanceof DecideAttendanceExcuseCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return DecideAttendanceExcuseResult::fromIdempotency($cached);
        }
        $note = ExcuseDecisionGuard::note($command->approve, $command->note);

        $data = $this->unitOfWork->transaction(function () use ($command, $note): array {
            $excuse = ExcuseDecisionGuard::assertDecidable(
                $this->excuses->lock($command->excuseId), $command->excuseId, $command->schoolId, $command->academicYearId, $command->expectedVersion,
            );
            $changed = $command->approve ? $this->application->apply($excuse, $command->actorUserId) : [];
            $status = $command->approve ? ExcuseStatus::Approved : ExcuseStatus::Rejected;
            $this->excuses->decide($excuse->id, $excuse->version, $status->value, $command->actorUserId, $note, count($changed));
            $this->outbox->stage(new AttendanceWorkflowEvent(
                $command->approve ? AttendanceWorkflowEvent::EXCUSE_APPROVED : AttendanceWorkflowEvent::EXCUSE_REJECTED,
                $command->schoolId, $command->academicYearId, 'attendance_excuse', $excuse->id, $command->actorUserId,
                ['status' => ExcuseStatus::Pending->value, 'records' => $changed],
                ['status' => $status->value, 'note' => $note, 'student_id' => $excuse->studentId, 'submitted_by' => $excuse->submittedBy],
                new \DateTimeImmutable,
            ));

            return ['excuse_id' => $excuse->id, 'status' => $status->value, 'applied_records' => count($changed)];
        });

        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $data);

        return DecideAttendanceExcuseResult::success($data);
    }
}
