<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Imports\Results\ExecuteImportCommitResult;
use App\Application\Imports\Support\ImportEngine;

final class ExecuteImportCommitHandler implements CommandHandler
{
    public function __construct(
        private readonly ImportEngine $engine,
    ) {}

    public function handle(Command $command): ExecuteImportCommitResult
    {
        assert($command instanceof ExecuteImportCommitCommand);
        $this->engine->commit($command->schoolId, $command->batchId, $command->userId);

        return ExecuteImportCommitResult::success($command->batchId);
    }
}
