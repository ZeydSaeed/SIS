<?php

namespace App\Application\Attendance\Contracts;

/**
 * Stores an excuse attachment through the Documents module (object storage + documents.files metadata,
 * size / MIME validation, safe storage key). Returns the documents.files id.
 */
interface ExcuseDocumentStorageInterface
{
    public function store(int $schoolId, int $studentId, int $excuseType, string $fileName, string $mimeType, string $contents, ?int $uploadedBy, string $idempotencyKey): int;
}
