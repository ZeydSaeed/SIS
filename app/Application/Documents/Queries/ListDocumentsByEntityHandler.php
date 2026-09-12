<?php

namespace App\Application\Documents\Queries;

use App\Application\Documents\DTOs\DocumentFileDTO;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;

final class ListDocumentsByEntityHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
    ) {}

    /**
     * @return list<DocumentFileDTO>
     */
    public function handle(ListDocumentsByEntityQuery $query): array
    {
        $entityType = strtolower(trim($query->entityType));
        $items = [];
        foreach ($this->documents->listByEntity($query->schoolId, $entityType, $query->entityId) as $row) {
            $items[] = new DocumentFileDTO(
                $row->id,
                $row->schoolId,
                $row->entityType,
                $row->entityId,
                $row->documentType,
                $row->storageKey,
                $row->fileName,
                $row->mimeType,
                $row->fileSize,
                $row->fileHash,
                $row->uploadedBy,
                $row->createdAt,
            );
        }

        return $items;
    }
}
