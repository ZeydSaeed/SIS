<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\ReopenExamEnrollmentResult;
use App\Domain\Exams\Events\ExamEnrollmentReopened;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;

final class ReopenExamEnrollmentHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.ReopenExamEnrollment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): ReopenExamEnrollmentResult
    {
        assert($command instanceof ReopenExamEnrollmentCommand);

        $this->authority->assertCan(
            ExamAdministrationAction::UpdateEnrollment,
            $command->actorUserId,
            $command->schoolId,
        );

        if ($command->idempotencyKey !== null && $command->idempotencyKey !== '') {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return ReopenExamEnrollmentResult::fromIdempotency(
                    (int) $cached['exam_enrollment_id'],
                    (int) $cached['exam_session_id'],
                    (int) $cached['enrollment_id'],
                    (int) $cached['status'],
                );
            }
        }

        $enrollment = $this->exams->findExamEnrollmentByIdAndSchool(
            $command->examEnrollmentId,
            $command->schoolId,
        );
        if ($enrollment === null) {
            return ReopenExamEnrollmentResult::failure(['exam_enrollment.not_found']);
        }
        if ($enrollment->status === ExamEnrollmentStatus::Registered->value) {
            return ReopenExamEnrollmentResult::failure(['exam_enrollment.already_open']);
        }
        if ($enrollment->status !== ExamEnrollmentStatus::Withdrawn->value) {
            return ReopenExamEnrollmentResult::failure(['exam_enrollment.not_reopenable']);
        }

        $ok = $this->unitOfWork->transaction(function () use ($command, $enrollment): bool {
            if (! $this->exams->reopenExamEnrollment($command->examEnrollmentId, $command->schoolId)) {
                return false;
            }

            $this->outbox->stage(new ExamEnrollmentReopened(
                examEnrollmentId: $command->examEnrollmentId,
                examSessionId: $enrollment->examSessionId,
                enrollmentId: $enrollment->enrollmentId,
                schoolId: $enrollment->schoolId,
                previousStatus: $enrollment->status,
                status: ExamEnrollmentStatus::Registered->value,
                reopenedBy: $command->actorUserId,
                occurredAt: new \DateTimeImmutable,
            ), $command->correlationId);

            return true;
        });

        if (! $ok) {
            return ReopenExamEnrollmentResult::failure(['exam_enrollment.reopen_failed']);
        }

        if ($command->idempotencyKey !== null && $command->idempotencyKey !== '') {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'exam_enrollment_id' => $command->examEnrollmentId,
                'exam_session_id' => $enrollment->examSessionId,
                'enrollment_id' => $enrollment->enrollmentId,
                'status' => ExamEnrollmentStatus::Registered->value,
            ]);
        }

        return ReopenExamEnrollmentResult::success(
            $command->examEnrollmentId,
            $enrollment->examSessionId,
            $enrollment->enrollmentId,
            ExamEnrollmentStatus::Registered->value,
        );
    }
}
