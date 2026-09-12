<?php

namespace App\Domain\Documents\Data;

final readonly class DocumentFileSnapshot
{
    public function __construct(
        public int $id,
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
        public string $createdAt,
    ) {}
}
