<?php

namespace App\Domain\Admission\Data;

final readonly class RegisterApplicationDocumentData
{
    public function __construct(
        public int $applicationId,
        public int $documentType,
        public string $storageKey,
        public string $fileName,
        public string $fileHash,
    ) {}
}
