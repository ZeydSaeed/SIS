<?php

namespace App\Application\Documents\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Documents\Contracts\DocumentObjectStoragePort;
use App\Application\Documents\Results\UploadDocumentResult;
use App\Domain\Documents\Events\DocumentUploaded;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;
use App\Domain\Documents\Support\DocumentIdempotencyGuard;
use App\Domain\Documents\Support\DocumentUploadRules;

final class UploadDocumentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UploadDocument';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly DocumentRepositoryInterface $documents,
        private readonly DocumentObjectStoragePort $objectStorage,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UploadDocumentResult
    {
        assert($command instanceof UploadDocumentCommand);
        $key = DocumentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UploadDocumentResult::fromIdempotency(
                (int) $cached['document_id'],
                (string) $cached['storage_key'],
            );
        }

        $entityType = strtolower(trim($command->entityType));
        $errors = DocumentUploadRules::validate(
            $entityType,
            $command->documentType,
            $command->fileName,
            $command->mimeType,
            $command->contents,
            $command->maxBytes,
            $command->allowedMimes,
        );
        if ($errors !== []) {
            return UploadDocumentResult::failure($errors);
        }

        $fileName = trim($command->fileName);
        $mimeType = strtolower(trim($command->mimeType));
        $fileSize = strlen($command->contents);
        $fileHash = hash('sha256', $command->contents);
        $storageKey = DocumentUploadRules::buildStorageKey(
            $command->schoolId,
            $entityType,
            $command->entityId,
            $fileName,
        );

        $this->objectStorage->put($storageKey, $command->contents);

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $entityType, $storageKey, $fileName, $mimeType, $fileSize, $fileHash, $at): int {
            $id = $this->documents->create(
                $command->schoolId,
                $entityType,
                $command->entityId,
                $command->documentType,
                $storageKey,
                $fileName,
                $mimeType,
                $fileSize,
                $fileHash,
                $command->uploadedBy,
                $at,
            );
            $this->outbox->stage(new DocumentUploaded(
                $id,
                $command->schoolId,
                $entityType,
                $command->entityId,
                $storageKey,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'document_id' => $id,
                'storage_key' => $storageKey,
            ]);

            return $id;
        });

        return UploadDocumentResult::success($id, $storageKey);
    }
}
