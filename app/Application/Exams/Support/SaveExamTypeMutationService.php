<?php

namespace App\Application\Exams\Support;

use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Exams\Commands\SaveExamTypeCommand;
use App\Application\Exams\Commands\SaveExamTypeHandler;
use App\Domain\Exams\Events\ExamTypeSaved;
use App\Domain\Exams\Exceptions\ExamTypeException;
use App\Domain\Exams\Repositories\ExamTypeRepositoryInterface;
use App\Domain\Exams\Services\ExamTypeRules;
use App\Domain\Exams\Support\ExamIdempotencyGuard;

/** Runs inside the handler's transaction: replay, lock, rules, write, outbox, idempotency record. */
final class SaveExamTypeMutationService
{
    public function __construct(
        private readonly ExamTypeRepositoryInterface $types,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    /**
     * @return array{_replay: bool, exam_type_id: int, created: bool}
     */
    public function execute(SaveExamTypeCommand $command, string $code, string $name, int $weight, string $fingerprint): array
    {
        $cached = $this->idempotency->find($command->idempotencyKey, SaveExamTypeHandler::COMMAND_NAME);
        if ($cached !== null) {
            ExamIdempotencyGuard::assertFingerprintMatch($cached, $fingerprint);

            return ['_replay' => true, 'exam_type_id' => (int) $cached['exam_type_id'], 'created' => (bool) $cached['created']];
        }

        $previousWeight = null;
        if ($command->examTypeId !== null) {
            $type = $this->types->lockForSchool($command->examTypeId, $command->schoolId)
                ?? throw ExamTypeException::notFound();
            ExamTypeRules::assertEditable($type, $code, $weight);
            $previousWeight = $type->weightPercentage;
        }

        if ($this->types->codeTaken($command->schoolId, $code, $command->examTypeId)) {
            throw ExamTypeException::codeTaken();
        }

        $created = $command->examTypeId === null;
        $id = $created
            ? $this->types->insert($command->schoolId, $code, $name, $weight)
            : $command->examTypeId;
        if (! $created) {
            $this->types->update($id, $command->schoolId, $code, $name, $weight);
        }

        $this->outbox->stage(new ExamTypeSaved(
            examTypeId: $id,
            schoolId: $command->schoolId,
            code: $code,
            name: $name,
            weightPercentage: $weight,
            previousWeightPercentage: $previousWeight,
            created: $created,
            savedBy: $command->actorUserId,
            occurredAt: new \DateTimeImmutable,
        ), $command->correlationId);

        $payload = ExamIdempotencyGuard::withFingerprint(['exam_type_id' => $id, 'created' => $created], $fingerprint, $command->schoolId);
        $this->idempotency->store($command->idempotencyKey, SaveExamTypeHandler::COMMAND_NAME, $payload);

        return ['_replay' => false, 'exam_type_id' => $id, 'created' => $created];
    }
}
