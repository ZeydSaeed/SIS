<?php

namespace App\Application\Leave\Contracts;

/**
 * Attachments of a leave request through the Documents module (object storage + documents.files metadata with
 * entity_type 'leave_request'; size and MIME checked there). Returns the documents.files id.
 */
interface LeaveAttachmentStorageInterface
{
    public const ENTITY_TYPE = 'leave_request';

    public function store(int $schoolId, int $requestId, bool $medical, string $fileName, string $mimeType, string $contents, ?int $uploadedBy, string $idempotencyKey): int;

    public function count(int $schoolId, int $requestId): int;
}
