<?php

namespace App\Application\Exams\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Exams\Commands\UpdateExamSessionCommand;
use App\Application\Exams\Commands\UpdateExamSessionHandler;
use App\Domain\Exams\Events\ExamSessionUpdated;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\Support\ExamSessionUpdateGuard;

/**
 * Keeps UpdateExamSessionHandler under architecture complexity limits.
 */
final class UpdateExamSessionMutationService
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array{
     *   session_date?: string,
     *   start_time?: string,
     *   end_time?: string,
     *   room_id?: int|null,
     *   max_grade?: int,
     *   pass_grade?: int
     * }
     */
    public function metadataFields(UpdateExamSessionCommand $command): array
    {
        $metadata = [];
        if ($command->sessionDate !== null) {
            $metadata['session_date'] = $command->sessionDate;
        }
        if ($command->startTime !== null) {
            $metadata['start_time'] = $command->startTime;
        }
        if ($command->endTime !== null) {
            $metadata['end_time'] = $command->endTime;
        }
        if ($command->roomIdProvided) {
            $metadata['room_id'] = $command->roomId;
        }
        if ($command->maxGrade !== null) {
            $metadata['max_grade'] = $command->maxGrade;
        }
        if ($command->passGrade !== null) {
            $metadata['pass_grade'] = $command->passGrade;
        }

        return $metadata;
    }

    /**
     * @param  array{
     *   session_date?: string,
     *   start_time?: string,
     *   end_time?: string,
     *   room_id?: int|null,
     *   max_grade?: int,
     *   pass_grade?: int
     * }  $metadata
     * @return array<string, mixed>
     */
    public function execute(UpdateExamSessionCommand $command, array $metadata, string $fingerprint): array
    {
        $cached = $this->idempotency->find($command->idempotencyKey, UpdateExamSessionHandler::COMMAND_NAME);
        if ($cached !== null) {
            ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

            return [
                '_replay' => true,
                'exam_session_id' => (int) $cached['exam_session_id'],
                'exam_id' => (int) $cached['exam_id'],
                'status' => (int) $cached['status'],
            ];
        }

        $session = $this->exams->lockSessionByIdAndSchool($command->examSessionId, $command->schoolId);
        if ($session === null) {
            throw ExamValidationException::sessionNotFound();
        }

        $hasCurrentGrade = $this->exams->hasCurrentGradeForSession($session->id, $command->schoolId);
        ExamSessionUpdateGuard::assertMetadataAllowed($session, $metadata, $hasCurrentGrade);
        ExamSessionUpdateGuard::assertResolvedValues($session, $metadata);

        if (array_key_exists('room_id', $metadata)
            && $metadata['room_id'] !== null
            && ! $this->exams->roomBelongsToSchool((int) $metadata['room_id'], $command->schoolId)) {
            throw ExamValidationException::roomNotInSchool();
        }

        $this->exams->updateSessionAllowlisted($session->id, $command->schoolId, $metadata);

        $this->outbox->stage(new ExamSessionUpdated(
            examSessionId: $session->id,
            examId: $session->examId,
            schoolId: $command->schoolId,
            status: $session->status,
            changedFields: $metadata,
            updatedBy: $command->actorUserId,
            occurredAt: new \DateTimeImmutable,
        ), $command->correlationId);

        $resultPayload = ExamIdempotencyGuard::withFingerprint([
            'exam_session_id' => $session->id,
            'exam_id' => $session->examId,
            'status' => $session->status,
        ], $fingerprint, $command->schoolId);

        $this->idempotency->store($command->idempotencyKey, UpdateExamSessionHandler::COMMAND_NAME, $resultPayload);

        return $resultPayload + ['_replay' => false];
    }
}
