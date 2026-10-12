<?php

namespace App\Infrastructure\Attendance;

use App\Application\Attendance\Contracts\ExcuseDocumentStorageInterface;
use App\Application\Documents\Commands\UploadDocumentCommand;
use App\Application\Documents\Commands\UploadDocumentHandler;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Documents\ValueObjects\DocumentType;

final class DocumentsExcuseDocumentStorage implements ExcuseDocumentStorageInterface
{
    /** Medical excuses are filed as medical documents; the rest as «other». */
    private const MEDICAL_EXCUSE = 1;

    public function __construct(
        private readonly UploadDocumentHandler $upload,
    ) {}

    public function store(int $schoolId, int $studentId, int $excuseType, string $fileName, string $mimeType, string $contents, ?int $uploadedBy, string $idempotencyKey): int
    {
        $result = $this->upload->handle(new UploadDocumentCommand(
            schoolId: $schoolId,
            entityType: 'student',
            entityId: $studentId,
            documentType: $excuseType === self::MEDICAL_EXCUSE ? DocumentType::Medical : DocumentType::Other,
            fileName: $fileName,
            mimeType: $mimeType,
            contents: $contents,
            maxBytes: (int) config('sis.documents.max_bytes', 10 * 1024 * 1024),
            allowedMimes: (array) config('sis.documents.allowed_mimes', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']),
            uploadedBy: $uploadedBy,
            idempotencyKey: $idempotencyKey.':document',
        ));
        if ($result->failed() || $result->documentId === null) {
            throw AttendanceRuleViolation::excuseInvalid('document:'.($result->errors[0] ?? 'upload_failed'));
        }

        return (int) $result->documentId;
    }
}
