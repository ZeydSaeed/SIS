<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Student\Results\RegisterStudentDocumentResult;
use App\Domain\Student\Events\StudentDocumentRegistered;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Student\Services\RegisterStudentDocumentGuard;
use App\Domain\Student\Support\StudentIdempotencyGuard;

final class RegisterStudentDocumentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RegisterStudentDocument';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentDocumentRepositoryInterface $documents,
        private readonly RegisterStudentDocumentGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RegisterStudentDocumentResult
    {
        assert($command instanceof RegisterStudentDocumentCommand);
        $key = StudentIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RegisterStudentDocumentResult::fromIdempotency((int) $cached['document_id']);
        }

        $error = $this->guard->rejectionCode(
            $command->schoolId,
            $command->studentId,
            $command->documentType,
            $command->storageKey,
            $command->fileName,
            $command->mimeType,
            $command->fileSize,
            $command->fileHash,
        );
        if ($error !== null) {
            return RegisterStudentDocumentResult::failure([$error]);
        }

        $id = $this->unitOfWork->transaction(function () use ($command, $key): int {
            $id = $this->documents->create(
                $command->schoolId,
                $command->studentId,
                $command->documentType,
                trim($command->storageKey),
                trim($command->fileName),
                trim($command->mimeType),
                $command->fileSize,
                strtolower(trim($command->fileHash)),
                $command->uploadedBy,
                (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            );
            $this->outbox->stage(new StudentDocumentRegistered(
                $id,
                $command->schoolId,
                $command->studentId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['document_id' => $id]);

            return $id;
        });

        return RegisterStudentDocumentResult::success($id);
    }
}
