<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Application\Documents\Contracts\DocumentObjectStoragePort;
use App\Application\Imports\Contracts\ImportJobDispatcher;
use App\Application\Imports\Results\UploadImportResult;
use App\Application\Imports\Support\ImportProfileRegistry;
use App\Domain\Imports\Repositories\ImportBatchRepositoryInterface;

final class UploadImportHandler implements CommandHandler
{
    private const COMMAND_NAME = 'UploadImport';

    public const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly ImportBatchRepositoryInterface $batches,
        private readonly ImportProfileRegistry $profiles,
        private readonly DocumentObjectStoragePort $storage,
        private readonly ImportJobDispatcher $jobs,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): UploadImportResult
    {
        assert($command instanceof UploadImportCommand);
        $key = trim((string) $command->idempotencyKey);
        $cached = $key === '' ? null : $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return UploadImportResult::fromIdempotency((int) $cached['id']);
        }

        $profile = $this->profiles->get($command->kind);
        $extension = strtolower(pathinfo($command->fileName, PATHINFO_EXTENSION));
        $error = match (true) {
            $profile === null => 'import.kind_invalid',
            $profile->needsYear() && $command->academicYearId === null => 'import.year_required',
            $command->contents === '' || strlen($command->contents) > self::MAX_BYTES => 'import.file_size_invalid',
            ! in_array($extension, ['xlsx', 'csv'], true) => 'import.file_type_invalid',
            default => null,
        };
        if ($error !== null) {
            return UploadImportResult::failure($error);
        }

        $storageKey = sprintf('imports/%d/%s.%s', $command->schoolId, bin2hex(random_bytes(16)), $extension);
        $this->storage->put($storageKey, $command->contents);
        $id = $this->unitOfWork->transaction(function () use ($command, $storageKey, $key): int {
            $id = $this->batches->create($command->schoolId, $command->academicYearId, $command->kind, $command->fileName, $storageKey, $command->userId, (new \DateTimeImmutable)->format('Y-m-d H:i:s'));
            if ($key !== '') {
                $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $id]);
            }

            return $id;
        });
        $this->jobs->parse($command->schoolId, $id);

        return UploadImportResult::success($id);
    }
}
