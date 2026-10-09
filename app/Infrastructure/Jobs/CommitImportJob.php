<?php

namespace App\Infrastructure\Jobs;

use App\Application\Imports\Commands\ExecuteImportCommitCommand;
use App\Application\Imports\Commands\ExecuteImportCommitHandler;
use App\Security\Context\SchoolContextScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** «استيراد Excel»: imports the valid rows of a confirmed batch, inside the school's RLS context. */
final class CommitImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $batchId,
        public readonly ?int $userId,
    ) {}

    public function handle(SchoolContextScope $scope, ExecuteImportCommitHandler $handler): void
    {
        $scope->run($this->schoolId, fn () => $handler->handle(new ExecuteImportCommitCommand($this->schoolId, $this->batchId, $this->userId, 'import-commit-'.$this->batchId)));
    }
}
