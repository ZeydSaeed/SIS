<?php

namespace App\Application\Imports\Commands;

use App\Application\Contracts\Command;

/** «رفع الملف»: stores the spreadsheet and queues its parsing (validation + preview — nothing is imported yet). */
final readonly class UploadImportCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public ?int $academicYearId,
        public string $kind,
        public string $fileName,
        public string $contents,
        public ?int $userId,
        public ?string $idempotencyKey,
    ) {}
}
