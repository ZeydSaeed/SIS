<?php

namespace App\Infrastructure\Jobs;

use App\Application\Imports\Commands\ParseImportCommand;
use App\Application\Imports\Commands\ParseImportHandler;
use App\Security\Context\SchoolContextScope;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** «استيراد Excel»: parses + validates an uploaded batch off the HTTP request, inside the school's RLS context. */
final class ParseImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public readonly int $schoolId,
        public readonly int $batchId,
    ) {}

    public function handle(SchoolContextScope $scope, ParseImportHandler $handler): void
    {
        $scope->run($this->schoolId, fn () => $handler->handle(new ParseImportCommand($this->schoolId, $this->batchId, 'import-parse-'.$this->batchId)));
    }
}
