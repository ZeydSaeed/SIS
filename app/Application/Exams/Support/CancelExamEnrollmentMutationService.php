<?php

namespace App\Application\Exams\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Exams\Commands\CancelExamEnrollmentCommand;
use App\Application\Exams\Commands\CancelExamEnrollmentHandler;
use App\Domain\Exams\Events\ExamEnrollmentCancelled;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\CancelExamEnrollmentGuard;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;

/**
 * Keeps CancelExamEnrollmentHandler under architecture complexity limits.
 */
final class CancelExamEnrollmentMutationService
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(CancelExamEnrollmentCommand $command, string $fingerprint): array
    {
        $cached = $this->idempotency->find($command->idempotencyKey, CancelExamEnrollmentHandler::COMMAND_NAME);
        if ($cached !== null) {
            ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

            return [
                '_replay' => true,
                'exam_enrollment_id' => (int) $cached['exam_enrollment_id'],
                'exam_session_id' => (int) $cached['exam_session_id'],
                'enrollment_id' => (int) $cached['enrollment_id'],
                'status' => (int) $cached['status'],
                'noop' => (bool) ($cached['noop'] ?? false),
            ];
        }

        $enrollment = $this->exams->lockExamEnrollmentByIdAndSchool(
            $command->examEnrollmentId,
            $command->schoolId,
        );
        if ($enrollment === null
            || $enrollment->examSessionId !== $command->examSessionId
            || $enrollment->enrollmentId !== $command->enrollmentId) {
            throw ExamValidationException::examEnrollmentNotFound();
        }

        $session = $this->exams->lockSessionByIdAndSchool($enrollment->examSessionId, $command->schoolId);
        if ($session === null) {
            throw ExamValidationException::sessionNotFound();
        }

        $alreadyWithdrawn = CancelExamEnrollmentGuard::assertCancellable(
            $enrollment,
            $this->exams->hasCurrentGradeForExamEnrollment($enrollment->id, $command->schoolId),
        );

        $status = ExamEnrollmentStatus::Withdrawn->value;

        if ($alreadyWithdrawn) {
            $resultPayload = ExamIdempotencyGuard::withFingerprint([
                'exam_enrollment_id' => $enrollment->id,
                'exam_session_id' => $enrollment->examSessionId,
                'enrollment_id' => $enrollment->enrollmentId,
                'status' => $status,
                'noop' => true,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, CancelExamEnrollmentHandler::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false, '_noop' => true];
        }

        $previousStatus = $enrollment->status;

        $this->exams->updateExamEnrollmentAllowlisted($enrollment->id, $command->schoolId, [
            'status' => $status,
        ]);

        $this->outbox->stage(new ExamEnrollmentCancelled(
            examEnrollmentId: $enrollment->id,
            examSessionId: $enrollment->examSessionId,
            examId: $session->examId,
            schoolId: $command->schoolId,
            previousStatus: $previousStatus,
            cancelledBy: $command->actorUserId,
            occurredAt: new \DateTimeImmutable,
            cause: 'exam_enrollment_cancel',
        ), $command->correlationId);

        $resultPayload = ExamIdempotencyGuard::withFingerprint([
            'exam_enrollment_id' => $enrollment->id,
            'exam_session_id' => $enrollment->examSessionId,
            'enrollment_id' => $enrollment->enrollmentId,
            'status' => $status,
            'noop' => false,
        ], $fingerprint, $command->schoolId);

        $this->idempotency->store($command->idempotencyKey, CancelExamEnrollmentHandler::COMMAND_NAME, $resultPayload);

        return $resultPayload + ['_replay' => false, '_noop' => false];
    }
}
