<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Imports\Results\ParseImportResult;
use App\Application\Imports\Support\ImportEngine;

final class ParseImportHandler implements CommandHandler
{
    public function __construct(
        private readonly ImportEngine $engine,
    ) {}

    public function handle(Command $command): ParseImportResult
    {
        assert($command instanceof ParseImportCommand);
        $this->engine->parse($command->schoolId, $command->batchId);

        return ParseImportResult::success($command->batchId);
    }
}
