<?php

namespace App\Domain\Student\Services;

use App\Domain\Student\Repositories\StudentDocumentRepositoryInterface;
use App\Domain\Student\ValueObjects\StudentDocumentType;

final class RegisterStudentDocumentGuard
{
    public function __construct(
        private readonly StudentDocumentRepositoryInterface $documents,
    ) {}

    public function rejectionCode(
        int $schoolId,
        int $studentId,
        int $documentType,
        string $storageKey,
        string $fileName,
        string $mimeType,
        int $fileSize,
        string $fileHash,
    ): ?string {
        if (! $this->documents->studentInSchool($schoolId, $studentId)) {
            return 'student.not_found';
        }
        if (! StudentDocumentType::isValid($documentType)) {
            return 'student.document_type_invalid';
        }
        if ($fileSize < 0) {
            return 'student.document_file_size_invalid';
        }
        $storageKey = trim($storageKey);
        $fileName = trim($fileName);
        $mimeType = trim($mimeType);
        $fileHash = strtolower(trim($fileHash));
        if ($storageKey === '' || $fileName === '' || $mimeType === '' || $fileHash === '') {
            return 'student.document_metadata_required';
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $fileHash)) {
            return 'student.document_file_hash_invalid';
        }

        return null;
    }
}
