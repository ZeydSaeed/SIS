<?php

namespace App\Application\Documents\Commands;

use App\Application\Contracts\Command;

final readonly class RegisterDocumentMetadataCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public string $entityType,
        public int $entityId,
        public int $documentType,
        public string $storageKey,
        public string $fileName,
        public string $mimeType,
        public int $fileSize,
        public string $fileHash,
        public ?int $uploadedBy,
        public ?string $idempotencyKey,
    ) {}
}
