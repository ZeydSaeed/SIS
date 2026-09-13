<?php

namespace App\Application\Documents\Commands;

use App\Application\Contracts\Command;

final readonly class UploadDocumentCommand implements Command
{
    /**
     * @param  list<string>  $allowedMimes
     */
    public function __construct(
        public int $schoolId,
        public string $entityType,
        public int $entityId,
        public int $documentType,
        public string $fileName,
        public string $mimeType,
        public string $contents,
        public int $maxBytes,
        public array $allowedMimes,
        public ?int $uploadedBy,
        public ?string $idempotencyKey,
    ) {}
}
