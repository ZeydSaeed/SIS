<?php

namespace App\Application\Student\DTOs;

final readonly class StudentDocumentDTO
{
    public function __construct(
        public int $id,
        public int $studentId,
        public int $documentType,
        public string $storageKey,
        public string $fileName,
        public string $mimeType,
        public int $fileSize,
        public string $fileHash,
        public int $status,
    ) {}
}
