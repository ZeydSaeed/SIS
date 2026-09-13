<?php

namespace App\Domain\Student\Support;

use App\Domain\Student\ValueObjects\StudentDocumentType;

final class StudentDocumentUploadRules
{
    /**
     * @param  list<string>  $allowedMimes
     * @return list<string>
     */
    public static function validate(
        int $documentType,
        string $fileName,
        string $mimeType,
        string $contents,
        int $maxBytes,
        array $allowedMimes,
    ): array {
        if (! StudentDocumentType::isValid($documentType)) {
            return ['student.document_type_invalid'];
        }
        if (trim($fileName) === '') {
            return ['student.document_file_name_invalid'];
        }
        $size = strlen($contents);
        if ($size < 1) {
            return ['student.document_file_empty'];
        }
        if ($size > $maxBytes) {
            return ['student.document_file_too_large'];
        }
        $mime = strtolower(trim($mimeType));
        $allowed = array_map('strtolower', $allowedMimes);
        if (! in_array($mime, $allowed, true)) {
            return ['student.document_mime_type_not_allowed'];
        }

        return [];
    }

    public static function buildStorageKey(int $schoolId, int $studentId, string $fileName): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', basename($fileName)) ?: 'file';
        $token = bin2hex(random_bytes(8));

        return sprintf(
            'schools/%d/student/%d/%s_%s',
            $schoolId,
            $studentId,
            $token,
            $safe,
        );
    }
}
