<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Results\RestoreStudentDocumentResult;
use App\Domain\Student\Events\StudentDocumentRestored;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Student\Support\StudentIdempotencyGuard;

final class RestoreStudentDocumentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RestoreStudentDocument';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentDocumentRepositoryInterface $documents,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RestoreStudentDocumentResult
    {
        assert($command instanceof RestoreStudentDocumentCommand);
        $key = StudentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RestoreStudentDocumentResult::fromIdempotency((int) $cached['document_id']);
        }

        $doc = $this->documents->findVoided($command->schoolId, $command->documentId);
        if ($doc === null) {
            return RestoreStudentDocumentResult::failure(['student.document_not_found']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key, $doc): void {
            $this->documents->restore($command->schoolId, $command->documentId);
            $this->outbox->stage(new StudentDocumentRestored(
                $command->documentId,
                $command->schoolId,
                $doc->studentId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['document_id' => $command->documentId]);
        });

        return RestoreStudentDocumentResult::success($command->documentId);
    }
}
