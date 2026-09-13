<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Documents\Contracts\DocumentObjectStoragePort;
use App\Application\Student\Results\UploadStudentDocumentResult;
use App\Domain\Student\Events\StudentDocumentUploaded;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Student\Support\StudentDocumentUploadRules;
use App\Domain\Student\Support\StudentIdempotencyGuard;

final class UploadStudentDocumentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UploadStudentDocument';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentDocumentRepositoryInterface $documents,
        private readonly DocumentObjectStoragePort $objectStorage,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UploadStudentDocumentResult
    {
        assert($command instanceof UploadStudentDocumentCommand);
        $key = StudentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UploadStudentDocumentResult::fromIdempotency(
                (int) $cached['document_id'],
                (string) $cached['storage_key'],
            );
        }

        if (! $this->documents->studentInSchool($command->schoolId, $command->studentId)) {
            return UploadStudentDocumentResult::failure(['student.not_found']);
        }

        $errors = StudentDocumentUploadRules::validate(
            $command->documentType,
            $command->fileName,
            $command->mimeType,
            $command->contents,
            $command->maxBytes,
            $command->allowedMimes,
        );
        if ($errors !== []) {
            return UploadStudentDocumentResult::failure($errors);
        }

        $fileName = trim($command->fileName);
        $mimeType = strtolower(trim($command->mimeType));
        $fileSize = strlen($command->contents);
        $fileHash = hash('sha256', $command->contents);
        $storageKey = StudentDocumentUploadRules::buildStorageKey(
            $command->schoolId,
            $command->studentId,
            $fileName,
        );

        $this->objectStorage->put($storageKey, $command->contents);

        $id = $this->unitOfWork->transaction(function () use ($command, $key, $storageKey, $fileName, $mimeType, $fileSize, $fileHash): int {
            $id = $this->documents->create(
                $command->schoolId,
                $command->studentId,
                $command->documentType,
                $storageKey,
                $fileName,
                $mimeType,
                $fileSize,
                $fileHash,
                $command->uploadedBy,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new StudentDocumentUploaded(
                $id,
                $command->schoolId,
                $command->studentId,
                $storageKey,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'document_id' => $id,
                'storage_key' => $storageKey,
            ]);

            return $id;
        });

        return UploadStudentDocumentResult::success($id, $storageKey);
    }
}
