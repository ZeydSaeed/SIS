<?php

namespace App\Application\Student\DTOs;

final readonly class StudentDocumentContentDTO
{
    public function __construct(
        public int $documentId,
        public string $fileName,
        public string $mimeType,
        public string $contents,
        public string $fileHash,
    ) {}
}
