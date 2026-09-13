<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;

final readonly class RegisterStudentDocumentCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
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
