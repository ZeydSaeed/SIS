<?php

namespace App\Application\Exams\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Exams\Commands\UpdateExamEnrollmentCommand;
use App\Application\Exams\Commands\UpdateExamEnrollmentHandler;
use App\Domain\Exams\Events\ExamEnrollmentUpdated;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\Support\UpdateExamEnrollmentGuard;

/**
 * Keeps UpdateExamEnrollmentHandler under architecture complexity limits.
 */
final class UpdateExamEnrollmentMutationService
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array{status?: int, seat_number?: string|null}
     */
    public function meaningfulChanges(UpdateExamEnrollmentCommand $command, int $currentStatus, ?string $currentSeatNumber): array
    {
        $changes = [];

        if ($command->status !== null && $command->status !== $currentStatus) {
            $changes['status'] = $command->status;
        }

        if ($command->seatNumberProvided && $command->seatNumber !== $currentSeatNumber) {
            $changes['seat_number'] = $command->seatNumber;
        }

        return $changes;
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(UpdateExamEnrollmentCommand $command, string $fingerprint): array
    {
        $cached = $this->idempotency->find($command->idempotencyKey, UpdateExamEnrollmentHandler::COMMAND_NAME);
        if ($cached !== null) {
            ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

            return [
                '_replay' => true,
                'exam_enrollment_id' => (int) $cached['exam_enrollment_id'],
                'exam_session_id' => (int) $cached['exam_session_id'],
                'enrollment_id' => (int) $cached['enrollment_id'],
                'status' => (int) $cached['status'],
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

        $changes = $this->meaningfulChanges($command, $enrollment->status, $enrollment->seatNumber);
        UpdateExamEnrollmentGuard::assertMeaningfulUpdate($changes);
        UpdateExamEnrollmentGuard::assertAllowed(
            enrollment: $enrollment,
            session: $session,
            changes: $changes,
            hasCurrentGrade: $this->exams->hasCurrentGradeForExamEnrollment(
                $enrollment->id,
                $command->schoolId,
            ),
        );

        $this->exams->updateExamEnrollmentAllowlisted($enrollment->id, $command->schoolId, $changes);

        $newStatus = array_key_exists('status', $changes) ? (int) $changes['status'] : $enrollment->status;
        $newSeat = array_key_exists('seat_number', $changes) ? $changes['seat_number'] : $enrollment->seatNumber;

        $this->outbox->stage(new ExamEnrollmentUpdated(
            examEnrollmentId: $enrollment->id,
            examSessionId: $enrollment->examSessionId,
            examId: $session->examId,
            schoolId: $command->schoolId,
            enrollmentId: $enrollment->enrollmentId,
            previousStatus: $enrollment->status,
            status: $newStatus,
            seatNumber: $newSeat,
            changedFields: $changes,
            updatedBy: $command->actorUserId,
            occurredAt: new \DateTimeImmutable,
        ), $command->correlationId);

        $resultPayload = ExamIdempotencyGuard::withFingerprint([
            'exam_enrollment_id' => $enrollment->id,
            'exam_session_id' => $enrollment->examSessionId,
            'enrollment_id' => $enrollment->enrollmentId,
            'status' => $newStatus,
        ], $fingerprint, $command->schoolId);

        $this->idempotency->store($command->idempotencyKey, UpdateExamEnrollmentHandler::COMMAND_NAME, $resultPayload);

        return $resultPayload + ['_replay' => false];
    }
}
