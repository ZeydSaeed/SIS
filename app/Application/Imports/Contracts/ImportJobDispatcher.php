<?php

namespace App\Application\Imports\Contracts;

/** Parsing and committing a spreadsheet run off the HTTP request (the queue; inline under `sync`). */
interface ImportJobDispatcher
{
    public function parse(int $schoolId, int $batchId): void;

    public function commit(int $schoolId, int $batchId, ?int $userId): void;
}
