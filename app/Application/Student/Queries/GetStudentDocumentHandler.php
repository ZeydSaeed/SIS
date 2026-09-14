<?php

namespace App\Application\Student\Queries;

use App\Application\Student\DTOs\StudentDocumentDTO;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;

final class GetStudentDocumentHandler
{
    public function __construct(
        private readonly StudentDocumentRepositoryInterface $documents,
    ) {}

    public function handle(GetStudentDocumentQuery $query): ?StudentDocumentDTO
    {
        $row = $this->documents->find($query->schoolId, $query->documentId);
        if ($row === null) {
            return null;
        }

        return new StudentDocumentDTO(
            id: $row->id,
            studentId: $row->studentId,
            documentType: $row->documentType,
            storageKey: $row->storageKey,
            fileName: $row->fileName,
            mimeType: $row->mimeType,
            fileSize: $row->fileSize,
            fileHash: $row->fileHash,
            status: $row->status,
        );
    }
}
