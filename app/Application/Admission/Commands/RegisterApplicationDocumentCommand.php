<?php

namespace App\Application\Admission\Commands;

use App\Application\Contracts\Command;

final readonly class RegisterApplicationDocumentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $applicationId,
        public int $documentType,
        public string $fileName,
        public ?string $storageKey = null,
        public ?string $fileHash = null,
        public ?string $idempotencyKey = null,
    ) {}
}
