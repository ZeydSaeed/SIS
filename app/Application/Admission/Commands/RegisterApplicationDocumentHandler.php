<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Admission\Results\RegisterApplicationDocumentResult;
use App\Domain\Admission\Data\RegisterApplicationDocumentData;
use App\Domain\Admission\Events\ApplicationDocumentRegistered;
use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use DomainException;

final class RegisterApplicationDocumentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RegisterApplicationDocument';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RegisterApplicationDocumentResult
    {
        assert($command instanceof RegisterApplicationDocumentCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return RegisterApplicationDocumentResult::fromIdempotency((int) $cached['document_id']);
            }
        }

        if ($command->documentType <= 0) {
            throw new DomainException('document_type must be greater than zero.');
        }

        $application = $this->admission->findApplicationForSchool($command->applicationId, $command->schoolId);
        if ($application === null) {
            throw ApplicationNotFoundException::forId($command->applicationId);
        }

        $storageKey = $command->storageKey
            ?? sprintf('admission/%d/%s', $command->applicationId, bin2hex(random_bytes(8)));
        $fileHash = $command->fileHash ?? hash('sha256', $storageKey.'|'.$command->fileName);

        $documentId = $this->unitOfWork->transaction(function () use ($command, $storageKey, $fileHash): int {
            $id = $this->admission->registerDocument(new RegisterApplicationDocumentData(
                applicationId: $command->applicationId,
                documentType: $command->documentType,
                storageKey: $storageKey,
                fileName: $command->fileName,
                fileHash: $fileHash,
            ));

            $this->outbox->stage(new ApplicationDocumentRegistered(
                documentId: $id,
                applicationId: $command->applicationId,
                schoolId: $command->schoolId,
                documentType: $command->documentType,
                occurredAt: new \DateTimeImmutable,
            ));

            return $id;
        });

        if ($command->idempotencyKey !== null) {
            $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                'document_id' => $documentId,
            ]);
        }

        return RegisterApplicationDocumentResult::success($documentId);
    }
}
