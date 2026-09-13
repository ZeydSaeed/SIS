<?php

namespace App\Application\Student\Queries;

use App\Application\Student\DTOs\StudentDocumentDTO;
use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;

final class ListStudentDocumentsHandler
{
    public function __construct(
        private readonly StudentDocumentRepositoryInterface $documents,
    ) {}

    /** @return list<StudentDocumentDTO>|null */
    public function handle(ListStudentDocumentsQuery $query): ?array
    {
        if (! $this->documents->studentInSchool($query->schoolId, $query->studentId)) {
            return null;
        }

        return array_map(
            fn ($row): StudentDocumentDTO => new StudentDocumentDTO(
                id: $row->id,
                studentId: $row->studentId,
                documentType: $row->documentType,
                storageKey: $row->storageKey,
                fileName: $row->fileName,
                mimeType: $row->mimeType,
                fileSize: $row->fileSize,
                fileHash: $row->fileHash,
                status: $row->status,
            ),
            $this->documents->listActiveForStudent($query->schoolId, $query->studentId),
        );
    }
}
