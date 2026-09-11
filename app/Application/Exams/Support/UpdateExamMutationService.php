<?php

namespace App\Application\Exams\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Exams\Commands\UpdateExamCommand;
use App\Application\Exams\Commands\UpdateExamHandler;
use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Events\ExamUpdated;
use App\Domain\Exams\Exceptions\ExamNotFoundException;
use App\Domain\Exams\Exceptions\ExamValidationException;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\Support\ExamIdempotencyGuard;
use App\Domain\Exams\Support\ExamUpdateGuard;
use App\Domain\Exams\ValueObjects\ExamStatus;

/**
 * Keeps UpdateExamHandler under architecture complexity limits.
 */
final class UpdateExamMutationService
{
    public function __construct(
        private readonly ExamRepositoryInterface $exams,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array{
     *   name?: string,
     *   start_date?: string,
     *   end_date?: string,
     *   exam_type_id?: int,
     *   term_id?: int
     * }
     */
    public function metadataFields(UpdateExamCommand $command): array
    {
        $metadata = [];
        if ($command->name !== null) {
            $metadata['name'] = $command->name;
        }
        if ($command->startDate !== null) {
            $metadata['start_date'] = $command->startDate;
        }
        if ($command->endDate !== null) {
            $metadata['end_date'] = $command->endDate;
        }
        if ($command->examTypeId !== null) {
            $metadata['exam_type_id'] = $command->examTypeId;
        }
        if ($command->termId !== null) {
            $metadata['term_id'] = $command->termId;
        }

        return $metadata;
    }

    /**
     * @param  array{
     *   name?: string,
     *   start_date?: string,
     *   end_date?: string,
     *   exam_type_id?: int,
     *   term_id?: int
     * }  $metadata
     * @return array<string, mixed>
     */
    public function execute(UpdateExamCommand $command, array $metadata, string $fingerprint): array
    {
        $cached = $this->idempotency->find($command->idempotencyKey, UpdateExamHandler::COMMAND_NAME);
        if ($cached !== null) {
            ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

            return [
                '_replay' => true,
                'exam_id' => (int) $cached['exam_id'],
                'status' => (int) $cached['status'],
            ];
        }

        $exam = $this->exams->lockByIdAndSchool($command->examId, $command->schoolId);
        if ($exam === null) {
            throw ExamNotFoundException::forId($command->examId);
        }

        $changed = $this->resolveChanges($exam, $command, $metadata);
        $newStatus = (int) ($changed['status'] ?? $exam->status);

        $this->exams->updateAllowlisted($exam->id, $command->schoolId, $changed);

        $this->outbox->stage(new ExamUpdated(
            examId: $exam->id,
            schoolId: $command->schoolId,
            academicYearId: $exam->academicYearId,
            status: $newStatus,
            changedFields: $changed,
            updatedBy: $command->actorUserId,
            occurredAt: new \DateTimeImmutable,
        ), $command->correlationId);

        $resultPayload = ExamIdempotencyGuard::withFingerprint([
            'exam_id' => $exam->id,
            'status' => $newStatus,
        ], $fingerprint, $command->schoolId);

        $this->idempotency->store($command->idempotencyKey, UpdateExamHandler::COMMAND_NAME, $resultPayload);

        return $resultPayload + ['_replay' => false];
    }

    /**
     * @param  array{
     *   name?: string,
     *   start_date?: string,
     *   end_date?: string,
     *   exam_type_id?: int,
     *   term_id?: int
     * }  $metadata
     * @return array<string, int|string>
     */
    private function resolveChanges(ExamSnapshot $exam, UpdateExamCommand $command, array $metadata): array
    {
        ExamUpdateGuard::assertMetadataAllowed($exam, $metadata);
        $this->assertDateAndReferences($exam, $metadata);

        $changed = $metadata;
        if ($command->targetStatus !== null) {
            $from = ExamStatus::from($exam->status);
            $to = ExamStatus::from($command->targetStatus);
            $counts = $this->exams->sessionStatusCounts($exam->id, $command->schoolId);
            ExamUpdateGuard::assertStatusTransition($from, $to, $counts);
            $changed['status'] = $to->value;
        }

        return $changed;
    }

    /**
     * @param  array{
     *   name?: string,
     *   start_date?: string,
     *   end_date?: string,
     *   exam_type_id?: int,
     *   term_id?: int
     * }  $metadata
     */
    private function assertDateAndReferences(ExamSnapshot $exam, array $metadata): void
    {
        $startDate = $metadata['start_date'] ?? $exam->startDate;
        $endDate = $metadata['end_date'] ?? $exam->endDate;
        if ($endDate < $startDate) {
            throw ExamValidationException::invalidDates();
        }

        if (isset($metadata['exam_type_id']) && ! $this->exams->examTypeExists((int) $metadata['exam_type_id'])) {
            throw ExamValidationException::missingExamType();
        }

        if (isset($metadata['term_id']) && ! $this->exams->termBelongsToAcademicYear((int) $metadata['term_id'], $exam->academicYearId)) {
            throw ExamValidationException::missingTerm();
        }
    }
}
