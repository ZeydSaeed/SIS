<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Imports\Results\CancelImportResult;
use App\Domain\Imports\Repositories\ImportBatchRepositoryInterface as Batches;

final class CancelImportHandler implements CommandHandler
{
    public function __construct(
        private readonly Batches $batches,
    ) {}

    public function handle(Command $command): CancelImportResult
    {
        assert($command instanceof CancelImportCommand);
        $batch = $this->batches->find($command->schoolId, $command->batchId);
        if ($batch === null) {
            return CancelImportResult::failure('import.batch_not_found');
        }
        if (! in_array($batch['status'], [Batches::PREVIEWED, Batches::FAILED], true)) {
            return CancelImportResult::failure('import.batch_not_previewed');
        }
        $this->batches->setStatus($command->schoolId, $command->batchId, Batches::CANCELLED, $batch['error'], (new \DateTimeImmutable)->format('Y-m-d H:i:s'));

        return CancelImportResult::success($command->batchId);
    }
}
