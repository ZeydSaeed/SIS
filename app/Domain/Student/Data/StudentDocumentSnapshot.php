<?php

namespace App\Domain\Student\Data;

final readonly class StudentDocumentSnapshot
{
    public function __construct(
        public int $id,
        public int $schoolId,
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
