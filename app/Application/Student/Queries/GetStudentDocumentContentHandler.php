<?php

namespace App\Application\Student\Queries;

use App\Application\Documents\Contracts\DocumentObjectStoragePort;
use App\Application\Student\DTOs\StudentDocumentContentDTO;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;

final class GetStudentDocumentContentHandler
{
    public function __construct(
        private readonly StudentDocumentRepositoryInterface $documents,
        private readonly DocumentObjectStoragePort $objectStorage,
    ) {}

    public function handle(GetStudentDocumentContentQuery $query): ?StudentDocumentContentDTO
    {
        $meta = $this->documents->findActive($query->schoolId, $query->documentId);
        if ($meta === null) {
            return null;
        }

        $contents = $this->objectStorage->get($meta->storageKey);
        if ($contents === null) {
            return null;
        }

        return new StudentDocumentContentDTO(
            documentId: $meta->id,
            fileName: $meta->fileName,
            mimeType: $meta->mimeType,
            contents: $contents,
            fileHash: $meta->fileHash,
        );
    }
}
