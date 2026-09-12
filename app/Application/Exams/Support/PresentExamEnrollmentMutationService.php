<?php

namespace App\Application\Exams\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Exams\Commands\PresentExamEnrollmentCommand;
use App\Application\Exams\Commands\PresentExamEnrollmentHandler;
use App\Domain\Exams\Events\ExamEnrollmentUpdated;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\Support\PresentExamEnrollmentGuard;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;

/**
 * Keeps PresentExamEnrollmentHandler under architecture complexity limits.
 */
final class PresentExamEnrollmentMutationService
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(PresentExamEnrollmentCommand $command, string $fingerprint): array
    {
        $cached = $this->idempotency->find($command->idempotencyKey, PresentExamEnrollmentHandler::COMMAND_NAME);
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

        $this->assertGradeStateDeterminable($enrollment->id, $command->schoolId);

        $alreadyPresent = PresentExamEnrollmentGuard::assertPresentable($enrollment, $session);
        $status = ExamEnrollmentStatus::Present->value;

        if ($alreadyPresent) {
            $resultPayload = ExamIdempotencyGuard::withFingerprint([
                'exam_enrollment_id' => $enrollment->id,
                'exam_session_id' => $enrollment->examSessionId,
                'enrollment_id' => $enrollment->enrollmentId,
                'status' => $status,
                'noop' => true,
            ], $fingerprint, $command->schoolId);

            $this->idempotency->store($command->idempotencyKey, PresentExamEnrollmentHandler::COMMAND_NAME, $resultPayload);

            return $resultPayload + ['_replay' => false, '_noop' => true];
        }

        $previousStatus = $enrollment->status;

        $this->exams->updateExamEnrollmentAllowlisted($enrollment->id, $command->schoolId, [
            'status' => $status,
        ]);

        $this->outbox->stage(new ExamEnrollmentUpdated(
            examEnrollmentId: $enrollment->id,
            examSessionId: $enrollment->examSessionId,
            examId: $session->examId,
            schoolId: $command->schoolId,
            enrollmentId: $enrollment->enrollmentId,
            previousStatus: $previousStatus,
            status: $status,
            seatNumber: $enrollment->seatNumber,
            changedFields: ['status' => $status],
            updatedBy: $command->actorUserId,
            occurredAt: new \DateTimeImmutable,
            cause: 'exam_enrollment_present',
        ), $command->correlationId);

        $resultPayload = ExamIdempotencyGuard::withFingerprint([
            'exam_enrollment_id' => $enrollment->id,
            'exam_session_id' => $enrollment->examSessionId,
            'enrollment_id' => $enrollment->enrollmentId,
            'status' => $status,
            'noop' => false,
        ], $fingerprint, $command->schoolId);

        $this->idempotency->store($command->idempotencyKey, PresentExamEnrollmentHandler::COMMAND_NAME, $resultPayload);

        return $resultPayload + ['_replay' => false, '_noop' => false];
    }

    private function assertGradeStateDeterminable(int $examEnrollmentId, int $schoolId): void
    {
        try {
            // HD-U09-005: CURRENT grade does not block; lookup must still succeed (fail closed).
            $this->exams->hasCurrentGradeForExamEnrollment($examEnrollmentId, $schoolId);
        } catch (ExamValidationException $e) {
            throw $e;
        } catch (\Throwable) {
            throw ExamValidationException::gradeLookupFailed();
        }
    }
}
