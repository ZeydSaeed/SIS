<?php

namespace App\Application\Exams\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Exams\Contracts\ExamAdministrationAuthorityPort;
use App\Application\Exams\Results\CancelExamResult;
use App\Domain\Exams\Events\ExamCancelled;
use App\Domain\Exams\Events\ExamEnrollmentCancelled;
use App\Domain\Exams\Events\ExamSessionCancelled;
use App\Domain\Exams\Exceptions\ExamNotFoundException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamCancelGuard;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamAdministrationAction;
use App\Domain\Exams\ValueObjects\ExamStatus;

final class CancelExamHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Exams.CancelExam';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
        private readonly ExamAdministrationAuthorityPort $authority,
    ) {}

    public function handle(Command $command): CancelExamResult
    {
        assert($command instanceof CancelExamCommand);

        if ($command->idempotencyKey === '') {
            throw ExamValidationException::missingIdempotencyKey();
        }

        $this->authority->assertCan(
            ExamAdministrationAction::Cancel,
            $command->actorUserId,
            $command->schoolId,
        );

        $fingerprint = ExamIdempotencyGuard::fingerprint(self::COMMAND_NAME, $command->schoolId, [
            'schema_version' => 1,
            'exam_id' => $command->examId,
        ]);

        $payload = $this->unitOfWork->transaction(function () use ($command, $fingerprint): array {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

                return [
                    '_replay' => true,
                    'exam_id' => (int) $cached['exam_id'],
                    'status' => (int) $cached['status'],
                    'cancelled_session_ids' => array_map('intval', $cached['cancelled_session_ids'] ?? []),
                    'withdrawn_enrollment_ids' => array_map('intval', $cached['withdrawn_enrollment_ids'] ?? []),
                ];
            }

            $exam = $this->exams->lockByIdAndSchool($command->examId, $command->schoolId);
            if ($exam === null) {
                throw ExamNotFoundException::forId($command->examId);
            }

            ExamCancelGuard::assertCancellable(
                $exam,
                $this->exams->hasCurrentGradeForExam($exam->id, $command->schoolId),
                $this->exams->hasCompletedSessionForExam($exam->id, $command->schoolId),
            );

            $cancelledSessions = $this->exams->cancelOpenSessionsForExam($exam->id, $command->schoolId);
            $withdrawnEnrollments = $this->exams->withdrawActiveEnrollmentsForExam($exam->id, $command->schoolId);

            $status = ExamStatus::Cancelled->value;
            $this->exams->updateAllowlisted($exam->id, $command->schoolId, ['status' => $status]);

            $occurredAt = new \DateTimeImmutable;
            $cancelledSessionIds = [];
            foreach ($cancelledSessions as $session) {
                $cancelledSessionIds[] = $session['id'];
                $this->outbox->stage(new ExamSessionCancelled(
                    examSessionId: $session['id'],
                    examId: $exam->id,
                    schoolId: $command->schoolId,
                    previousStatus: $session['previous_status'],
                    cancelledBy: $command->actorUserId,
                    occurredAt: $occurredAt,
                    cause: 'exam_cancel',
                ), $command->correlationId);
            }

            $withdrawnEnrollmentIds = [];
            foreach ($withdrawnEnrollments as $enrollment) {
                $withdrawnEnrollmentIds[] = $enrollment['id'];
                $this->outbox->stage(new ExamEnrollmentCancelled(
                    examEnrollmentId: $enrollment['id'],
                    examSessionId: $enrollment['exam_session_id'],
                    examId: $exam->id,
                    schoolId: $command->schoolId,
                    previousStatus: $enrollment['previous_status'],
                    cancelledBy: $command->actorUserId,
                    occurredAt: $occurredAt,
                    cause: 'exam_cancel',
                ), $command->correlationId);
            }

            $this->outbox->stage(new ExamCancelled(
                examId: $exam->id,
                schoolId: $command->schoolId,
                academicYearId: $exam->academicYearId,
                cancelledSessionIds: $cancelledSessionIds,
                withdrawnEnrollmentIds: $withdrawnEnrollmentIds,
                cancelledBy: $command->actorUserId,
                occurredAt: $occurredAt,
            ), $command->correlationId);

            $resultPayload = ExamIdempotencyGuard::withFingerprint([
                'exam_id' => $exam->id,
                'status' => $status,
                'cancelled_session_ids' => $cancelledSessionIds,
                'withdrawn_enrollment_ids' => $withdrawnEnrollmentIds,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false];
        });

        if ($payload['_replay'] === true) {
            return CancelExamResult::fromIdempotency(
                (int) $payload['exam_id'],
                (int) $payload['status'],
                $payload['cancelled_session_ids'],
                $payload['withdrawn_enrollment_ids'],
            );
        }

        return CancelExamResult::success(
            (int) $payload['exam_id'],
            (int) $payload['status'],
            $payload['cancelled_session_ids'],
            $payload['withdrawn_enrollment_ids'],
        );
    }
}
