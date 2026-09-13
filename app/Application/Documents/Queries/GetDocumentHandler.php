<?php

namespace App\Application\Documents\Queries;

use App\Application\Documents\DTOs\DocumentFileDTO;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;

final class GetDocumentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
    ) {}

    public function handle(GetDocumentQuery $query): ?DocumentFileDTO
    {
        $row = $this->documents->findByIdForSchool($query->schoolId, $query->documentId);
        if ($row === null) {
            return null;
        }

        return new DocumentFileDTO(
            id: $row->id,
            schoolId: $row->schoolId,
            entityType: $row->entityType,
            entityId: $row->entityId,
            documentType: $row->documentType,
            storageKey: $row->storageKey,
            fileName: $row->fileName,
            mimeType: $row->mimeType,
            fileSize: $row->fileSize,
            fileHash: $row->fileHash,
            uploadedBy: $row->uploadedBy,
            createdAt: $row->createdAt,
        );
    }
}
