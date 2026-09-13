<?php

namespace App\Application\Documents\Queries;

use App\Application\Documents\Contracts\DocumentObjectStoragePort;
use App\Application\Documents\DTOs\DocumentContentDTO;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;

final class GetDocumentContentHandler
{
    public function __construct(
        private readonly DocumentRepositoryInterface $documents,
        private readonly DocumentObjectStoragePort $objectStorage,
    ) {}

    public function handle(GetDocumentContentQuery $query): ?DocumentContentDTO
    {
        $meta = $this->documents->findByIdForSchool($query->schoolId, $query->documentId);
        if ($meta === null) {
            return null;
        }

        $contents = $this->objectStorage->get($meta->storageKey);
        if ($contents === null) {
            return null;
        }

        return new DocumentContentDTO(
            documentId: $meta->id,
            fileName: $meta->fileName,
            mimeType: $meta->mimeType,
            contents: $contents,
            fileHash: $meta->fileHash,
        );
    }
}
