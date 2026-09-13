<?php

namespace App\Domain\Documents\Support;

use App\Domain\Documents\ValueObjects\DocumentType;

final class DocumentUploadRules
{
    /**
     * @return list<string>
     */
    public static function validate(
        string $entityType,
        int $documentType,
        string $fileName,
        string $mimeType,
        string $contents,
        int $maxBytes,
        array $allowedMimes,
    ): array {
        if (! DocumentEntityTypes::isAllowed($entityType)) {
            return ['documents.entity_type_invalid'];
        }
        if (! DocumentType::isValid($documentType)) {
            return ['documents.document_type_invalid'];
        }
        if (trim($fileName) === '') {
            return ['documents.file_name_invalid'];
        }
        $size = strlen($contents);
        if ($size < 1) {
            return ['documents.file_empty'];
        }
        if ($size > $maxBytes) {
            return ['documents.file_too_large'];
        }
        $mime = strtolower(trim($mimeType));
        $allowed = array_map('strtolower', $allowedMimes);
        if (! in_array($mime, $allowed, true)) {
            return ['documents.mime_type_not_allowed'];
        }

        return [];
    }

    public static function buildStorageKey(
        int $schoolId,
        string $entityType,
        int $entityId,
        string $fileName,
    ): string {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($fileName)) ?: 'file';
        $token = bin2hex(random_bytes(8));

        return sprintf(
            'schools/%d/%s/%d/%s_%s',
            $schoolId,
            strtolower($entityType),
            $entityId,
            $token,
            $safe,
        );
    }
}
