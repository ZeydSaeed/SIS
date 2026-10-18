<?php

namespace App\Infrastructure\Leave;

use App\Application\Documents\Commands\UploadDocumentCommand;
use App\Application\Documents\Commands\UploadDocumentHandler;
use App\Application\Leave\Contracts\LeaveAttachmentStorageInterface;
use App\Domain\Documents\Repositories\DocumentRepositoryInterface;
use App\Domain\Documents\ValueObjects\DocumentType;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;

/** Leave attachments through the Documents module (validation, object storage, documents.files metadata). */
final class DocumentsLeaveAttachmentStorage implements LeaveAttachmentStorageInterface
{
    public function __construct(
        private readonly UploadDocumentHandler $upload,
        private readonly DocumentRepositoryInterface $documents,
    ) {}

    public function store(int $schoolId, int $requestId, bool $medical, string $fileName, string $mimeType, string $contents, ?int $uploadedBy, string $idempotencyKey): int
    {
        $result = $this->upload->handle(new UploadDocumentCommand(
            schoolId: $schoolId,
            entityType: self::ENTITY_TYPE,
            entityId: $requestId,
            documentType: $medical ? DocumentType::Medical : DocumentType::Other,
            fileName: $fileName,
            mimeType: $mimeType,
            contents: $contents,
            maxBytes: (int) config('sis.documents.max_bytes', 10 * 1024 * 1024),
            allowedMimes: (array) config('sis.documents.allowed_mimes', ['application/pdf', 'image/jpeg', 'image/png', 'image/webp']),
            uploadedBy: $uploadedBy,
            idempotencyKey: $idempotencyKey.':document',
        ));
        if ($result->failed() || $result->documentId === null) {
            throw LeaveRuleViolation::of('document_invalid', (string) ($result->errors[0] ?? 'upload_failed'));
        }

        return (int) $result->documentId;
    }

    public function count(int $schoolId, int $requestId): int
    {
        return count($this->documents->listByEntity($schoolId, self::ENTITY_TYPE, $requestId));
    }
}
