<?php

namespace App\Application\Exams\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Exams\Commands\CancelExamSessionCommand;
use App\Application\Exams\Commands\CancelExamSessionHandler;
use App\Domain\Exams\Events\ExamEnrollmentCancelled;
use App\Domain\Exams\Events\ExamSessionCancelled;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\Support\ExamSessionCancelGuard;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;

/**
 * Keeps CancelExamSessionHandler under architecture complexity limits.
 */
final class CancelExamSessionMutationService
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(CancelExamSessionCommand $command, string $fingerprint): array
    {
        $cached = $this->idempotency->find($command->idempotencyKey, CancelExamSessionHandler::COMMAND_NAME);
        if ($cached !== null) {
            ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

            return [
                '_replay' => true,
                'exam_session_id' => (int) $cached['exam_session_id'],
                'exam_id' => (int) $cached['exam_id'],
                'status' => (int) $cached['status'],
                'withdrawn_enrollment_ids' => array_map('intval', $cached['withdrawn_enrollment_ids'] ?? []),
            ];
        }

        $session = $this->exams->lockSessionByIdAndSchool($command->examSessionId, $command->schoolId);
        if ($session === null) {
            throw ExamValidationException::sessionNotFound();
        }

        $alreadyCancelled = ExamSessionCancelGuard::assertCancellable(
            $session,
            $this->exams->hasCurrentGradeForSession($session->id, $command->schoolId),
        );

        if ($alreadyCancelled) {
            $resultPayload = ExamIdempotencyGuard::withFingerprint([
                'exam_session_id' => $session->id,
                'exam_id' => $session->examId,
                'status' => ExamSessionStatus::Cancelled->value,
                'withdrawn_enrollment_ids' => [],
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, CancelExamSessionHandler::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false, '_noop' => true];
        }

        $previousStatus = $session->status;
        $status = ExamSessionStatus::Cancelled->value;

        $this->exams->updateSessionAllowlisted($session->id, $command->schoolId, [
            'status' => $status,
        ]);

        $withdrawn = $this->exams->withdrawActiveEnrollmentsForSession($session->id, $command->schoolId);
        $withdrawnIds = [];
        $occurredAt = new \DateTimeImmutable;

        $this->outbox->stage(new ExamSessionCancelled(
            examSessionId: $session->id,
            examId: $session->examId,
            schoolId: $command->schoolId,
            previousStatus: $previousStatus,
            cancelledBy: $command->actorUserId,
            occurredAt: $occurredAt,
            cause: 'exam_session_cancel',
        ), $command->correlationId);

        foreach ($withdrawn as $enrollment) {
            $withdrawnIds[] = $enrollment['id'];
            $this->outbox->stage(new ExamEnrollmentCancelled(
                examEnrollmentId: $enrollment['id'],
                examSessionId: $enrollment['exam_session_id'],
                examId: $session->examId,
                schoolId: $command->schoolId,
                previousStatus: $enrollment['previous_status'],
                cancelledBy: $command->actorUserId,
                occurredAt: $occurredAt,
                cause: 'exam_session_cancel',
            ), $command->correlationId);
        }

        $resultPayload = ExamIdempotencyGuard::withFingerprint([
            'exam_session_id' => $session->id,
            'exam_id' => $session->examId,
            'status' => $status,
            'withdrawn_enrollment_ids' => $withdrawnIds,
        ], $fingerprint, $command->schoolId);

        $this->idempotency->store($command->idempotencyKey, CancelExamSessionHandler::COMMAND_NAME, $resultPayload);

        return $resultPayload + ['_replay' => false, '_noop' => false];
    }
}
