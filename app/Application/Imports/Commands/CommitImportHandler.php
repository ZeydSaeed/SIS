<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Imports\Contracts\ImportJobDispatcher;
use App\Application\Imports\Results\CommitImportResult;
use App\Domain\Imports\Repositories\ImportBatchRepositoryInterface as Batches;

final class CommitImportHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CommitImport';

    public function __construct(
        private readonly Batches $batches,
        private readonly ImportJobDispatcher $jobs,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CommitImportResult
    {
        assert($command instanceof CommitImportCommand);
        $key = trim((string) $command->idempotencyKey);
        if ($key !== '' && $this->idempotency->find($key, self::COMMAND_NAME) !== null) {
            return CommitImportResult::fromIdempotency($command->batchId);
        }
        $batch = $this->batches->find($command->schoolId, $command->batchId);
        $error = match (true) {
            $batch === null => 'import.batch_not_found',
            $batch['status'] !== Batches::PREVIEWED => 'import.batch_not_previewed',
            $batch['valid_rows'] === 0 => 'import.nothing_to_import',
            default => null,
        };
        if ($error !== null) {
            return CommitImportResult::failure($error);
        }
        $this->batches->setStatus($command->schoolId, $command->batchId, Batches::COMMITTING, null, (new \DateTimeImmutable)->format('Y-m-d H:i:s'));
        if ($key !== '') {
            $this->idempotency->store($key, self::COMMAND_NAME, ['id' => $command->batchId]);
        }
        $this->jobs->commit($command->schoolId, $command->batchId, $command->userId);

        return CommitImportResult::success($command->batchId);
    }
}
