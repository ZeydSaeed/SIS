<?php

namespace App\Domain\Documents\Repositories;

use App\Domain\Documents\Data\DocumentFileSnapshot;

interface DocumentRepositoryInterface
{
    public function create(
        int $schoolId,
        string $entityType,
        int $entityId,
        int $documentType,
        string $storageKey,
        string $fileName,
        string $mimeType,
        int $fileSize,
        string $fileHash,
        ?int $uploadedBy,
        string $createdAt,
    ): int;

    public function findByIdForSchool(int $schoolId, int $documentId): ?DocumentFileSnapshot;

    /**
     * @return list<DocumentFileSnapshot>
     */
    public function listByEntity(int $schoolId, string $entityType, int $entityId): array;
}
