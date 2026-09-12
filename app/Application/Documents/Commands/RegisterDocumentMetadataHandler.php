<?php

namespace App\Application\Documents\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Documents\Results\RegisterDocumentMetadataResult;
use App\Domain\Documents\Events\DocumentMetadataRegistered;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;
use App\Domain\Documents\Support\DocumentIdempotencyGuard;
use App\Domain\Documents\ValueObjects\DocumentType;

final class RegisterDocumentMetadataHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RegisterDocumentMetadata';

    private const ALLOWED_ENTITY_TYPES = [
        'student',
        'teacher',
        'enrollment',
        'certificate',
        'qualification',
    ];

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly DocumentRepositoryInterface $documents,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RegisterDocumentMetadataResult
    {
        assert($command instanceof RegisterDocumentMetadataCommand);
        $key = DocumentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RegisterDocumentMetadataResult::fromIdempotency((int) $cached['document_id']);
        }

        $entityType = strtolower(trim($command->entityType));
        if (! in_array($entityType, self::ALLOWED_ENTITY_TYPES, true)) {
            return RegisterDocumentMetadataResult::failure(['documents.entity_type_invalid']);
        }
        if (! DocumentType::isValid($command->documentType)) {
            return RegisterDocumentMetadataResult::failure(['documents.document_type_invalid']);
        }
        if ($command->fileSize < 0) {
            return RegisterDocumentMetadataResult::failure(['documents.file_size_invalid']);
        }

        $storageKey = trim($command->storageKey);
        $fileName = trim($command->fileName);
        $mimeType = trim($command->mimeType);
        $fileHash = strtolower(trim($command->fileHash));
        if ($storageKey === '' || $fileName === '' || $mimeType === '' || $fileHash === '') {
            return RegisterDocumentMetadataResult::failure(['documents.metadata_required']);
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $fileHash)) {
            return RegisterDocumentMetadataResult::failure(['documents.file_hash_invalid']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $entityType, $storageKey, $fileName, $mimeType, $fileHash, $at): int {
            $id = $this->documents->create(
                $command->schoolId,
                $entityType,
                $command->entityId,
                $command->documentType,
                $storageKey,
                $fileName,
                $mimeType,
                $command->fileSize,
                $fileHash,
                $command->uploadedBy,
                $at,
            );
            $this->outbox->stage(new DocumentMetadataRegistered(
                $id,
                $command->schoolId,
                $entityType,
                $command->entityId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['document_id' => $id]);

            return $id;
        });

        return RegisterDocumentMetadataResult::success($id);
    }
}
