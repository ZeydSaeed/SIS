<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Results\VoidStudentDocumentResult;
use App\Domain\Student\Events\StudentDocumentVoided;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Student\Support\StudentIdempotencyGuard;

final class VoidStudentDocumentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'VoidStudentDocument';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentDocumentRepositoryInterface $documents,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): VoidStudentDocumentResult
    {
        assert($command instanceof VoidStudentDocumentCommand);
        $key = StudentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return VoidStudentDocumentResult::fromIdempotency((int) $cached['document_id']);
        }

        $doc = $this->documents->findActive($command->schoolId, $command->documentId);
        if ($doc === null) {
            return VoidStudentDocumentResult::failure(['student.document_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $doc): void {
            $this->documents->void($command->schoolId, $command->documentId);
            $this->outbox->stage(new StudentDocumentVoided(
                $command->documentId,
                $command->schoolId,
                $doc->studentId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['document_id' => $command->documentId]);
        });

        return VoidStudentDocumentResult::success($command->documentId);
    }
}
