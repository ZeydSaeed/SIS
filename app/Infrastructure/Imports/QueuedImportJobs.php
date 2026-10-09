<?php

namespace App\Infrastructure\Imports;

use App\Application\Imports\Contracts\ImportJobDispatcher;
use App\Infrastructure\Jobs\CommitImportJob;
use App\Infrastructure\Jobs\ParseImportJob;

final class QueuedImportJobs implements ImportJobDispatcher
{
    public function parse(int $schoolId, int $batchId): void
    {
        ParseImportJob::dispatch($schoolId, $batchId);
    }

    public function commit(int $schoolId, int $batchId, ?int $userId): void
    {
        CommitImportJob::dispatch($schoolId, $batchId, $userId);
    }
}
