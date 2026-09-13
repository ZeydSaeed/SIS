<?php

namespace App\Application\Student\Commands;

use App\Application\Contracts\Command;

final readonly class UploadStudentDocumentCommand implements Command
{
    /**
     * @param  list<string>  $allowedMimes
     */
    public function __construct(
        public int $schoolId,
        public int $studentId,
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
