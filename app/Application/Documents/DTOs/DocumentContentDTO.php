<?php

namespace App\Application\Documents\DTOs;

final readonly class DocumentContentDTO
{
    public function __construct(
        public int $documentId,
        public string $fileName,
        public string $mimeType,
        public string $contents,
        public string $fileHash,
    ) {}
}
